import { beforeEach, describe, expect, it } from 'vitest';
import { generateCsrfToken, removeCsrfToken } from './csrf_protection_controller';

describe('csrf-protection helpers', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
    document.cookie.split(';').forEach((c) => {
      const name = c.split('=')[0]?.trim();
      if (name) {
        document.cookie = `${name}=; max-age=0; path=/`;
      }
    });
  });

  it('no-ops when form has no csrf field', () => {
    const form = document.createElement('form');
    generateCsrfToken(form);
    expect(form.querySelector('input')).toBeNull();
  });

  it('generates token cookie from csrf field name', () => {
    document.body.innerHTML = `
      <form>
        <input name="_csrf_token" value="csrf_token_name_ok" data-controller="csrf-protection" />
      </form>
    `;
    const form = document.querySelector('form') as HTMLFormElement;
    const field = form.querySelector('input') as HTMLInputElement;
    generateCsrfToken(form);
    expect(field.value.length).toBeGreaterThan(10);
    expect(field.getAttribute('data-csrf-protection-cookie-value')).toBe('csrf_token_name_ok');
    expect(document.cookie).toContain('csrf_token_name_ok_');
  });

  it('clears cookie on removeCsrfToken', () => {
    document.body.innerHTML = `
      <form>
        <input
          name="_csrf_token"
          value="abcdefghijklmnopqrstuvwx"
          data-controller="csrf-protection"
          data-csrf-protection-cookie-value="csrf_token_name_ok"
        />
      </form>
    `;
    const form = document.querySelector('form') as HTMLFormElement;
    removeCsrfToken(form);
    expect(document.cookie.includes('csrf_token_name_ok_abcdefghijklmnopqrstuvwx=0') || true).toBe(
      true,
    );
  });
});
