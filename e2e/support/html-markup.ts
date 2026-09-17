import { expect, type Page, type Response } from '@playwright/test';

/**
 * Twig / HTML markup standardization helpers.
 *
 * Catches regressions like:
 * - bare text responses ("Access denied.") instead of kit Twig shells
 * - inline &lt;style&gt;/&lt;script&gt; without CSP nonce in the HTTP body
 *   (browsers ignore style-src unsafe-inline when a nonce is present)
 * - host CSS present but not applied (layout stuck on UA defaults)
 *
 * Nonce checks use the raw response HTML: after parse, browsers hide nonce
 * from the live DOM / page.content() serialization.
 */

export type MarkupReport = {
  styleCount: number;
  styleMissingNonce: number;
  inlineScriptCount: number;
  inlineScriptMissingNonce: number;
  bodyText: string;
  hasDoctype: boolean;
  title: string;
};

function isExecutableScriptType(typeRaw: string | undefined): boolean {
  const type = (typeRaw || 'text/javascript').trim().toLowerCase();
  return (
    type === '' ||
    type === 'module' ||
    type === 'text/javascript' ||
    type === 'application/javascript' ||
    type === 'text/ecmascript'
  );
}

/** Parse nonce presence from raw HTML (response body), not the live DOM. */
export function reportFromHtml(html: string): Pick<
  MarkupReport,
  'styleCount' | 'styleMissingNonce' | 'inlineScriptCount' | 'inlineScriptMissingNonce' | 'hasDoctype' | 'title'
> {
  const styleTags = [...html.matchAll(/<style(\s[^>]*)?>/gi)];
  let styleMissingNonce = 0;
  for (const match of styleTags) {
    const attrs = match[1] ?? '';
    if (!/\bnonce\s*=/i.test(attrs)) {
      styleMissingNonce += 1;
    }
  }

  const scriptTags = [...html.matchAll(/<script(\s[^>]*)?>/gi)];
  let inlineScriptCount = 0;
  let inlineScriptMissingNonce = 0;
  for (const match of scriptTags) {
    const attrs = match[1] ?? '';
    if (/\bsrc\s*=/i.test(attrs)) {
      continue;
    }
    const typeMatch = attrs.match(/\btype\s*=\s*(["'])(.*?)\1/i);
    if (!isExecutableScriptType(typeMatch?.[2])) {
      continue;
    }
    inlineScriptCount += 1;
    if (!/\bnonce\s*=/i.test(attrs)) {
      inlineScriptMissingNonce += 1;
    }
  }

  const titleMatch = html.match(/<title[^>]*>([^<]*)<\/title>/i);

  return {
    styleCount: styleTags.length,
    styleMissingNonce,
    inlineScriptCount,
    inlineScriptMissingNonce,
    hasDoctype: /<!doctype\s+html/i.test(html),
    title: (titleMatch?.[1] ?? '').trim(),
  };
}

export async function collectLiveBodyReport(page: Page): Promise<Pick<MarkupReport, 'bodyText'>> {
  const bodyText = await page.evaluate(() =>
    (document.body?.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 240),
  );
  return { bodyText };
}

export async function readResponseHtml(response: Response | null): Promise<string> {
  expect(response, 'navigation response').not.toBeNull();
  const html = await response!.text();
  expect(html.length, 'empty HTML response').toBeGreaterThan(0);
  return html;
}

/** Document shell expected from host Twig layouts. */
export async function assertTwigDocumentShell(page: Page, html: string): Promise<void> {
  await expect(page.locator('html')).toHaveAttribute('lang', /.+/);
  await expect(page.locator('head meta[charset], head meta[charset="utf-8" i]').first()).toBeAttached();
  await expect(page.locator('head meta[name="viewport"]').first()).toBeAttached();
  const report = reportFromHtml(html);
  expect(report.hasDoctype, 'HTML must include a doctype').toBe(true);
  expect(report.title.length, 'Twig shell should set <title>').toBeGreaterThan(0);
}

/**
 * Every host/vendor inline &lt;style&gt; in the response must carry a CSP nonce.
 * Empty style lists are allowed (CSS only via &lt;link&gt;).
 */
export function assertInlineStylesHaveCspNonce(html: string): void {
  const report = reportFromHtml(html);
  expect(
    report.styleMissingNonce,
    `inline <style> without nonce (${report.styleMissingNonce}/${report.styleCount})`,
  ).toBe(0);
}

/**
 * Executable inline &lt;script&gt; blocks in the response must carry a CSP nonce
 * (ContentSecurityPolicySubscriber stamps bare Twig scripts).
 */
export function assertInlineScriptsHaveCspNonce(html: string): void {
  const report = reportFromHtml(html);
  expect(
    report.inlineScriptMissingNonce,
    `inline <script> without nonce (${report.inlineScriptMissingNonce}/${report.inlineScriptCount})`,
  ).toBe(0);
}

export async function assertNotBareTextResponse(
  page: Page,
  options?: { forbiddenExact?: string[] },
): Promise<void> {
  const forbidden = options?.forbiddenExact ?? ['Access denied.', 'Access denied'];
  const { bodyText } = await collectLiveBodyReport(page);
  for (const phrase of forbidden) {
    expect(bodyText, `bare text response "${phrase}"`).not.toBe(phrase);
  }
  expect(bodyText.length, 'body text too sparse for a Twig shell').toBeGreaterThan(24);
  await expect(page.locator('html head title')).toBeAttached();
}

/** CSS from host Twig actually applied (not blocked by CSP). */
export async function assertComputedDisplay(
  page: Page,
  selector: string,
  expected: string | RegExp,
): Promise<void> {
  const display = await page.locator(selector).first().evaluate((el) => getComputedStyle(el).display);
  if (typeof expected === 'string') {
    expect(display, `${selector} display`).toBe(expected);
  } else {
    expect(display, `${selector} display`).toMatch(expected);
  }
}

export async function assertHtmlTwigStandardization(
  page: Page,
  html: string,
): Promise<void> {
  await assertTwigDocumentShell(page, html);
  await assertNotBareTextResponse(page);
  assertInlineStylesHaveCspNonce(html);
  assertInlineScriptsHaveCspNonce(html);
}
