const nameCheck = /^[-_a-zA-Z0-9]{4,22}$/;
const tokenCheck = /^[-_/+a-zA-Z0-9]{24,}$/;

interface MsCrypto {
  getRandomValues: Crypto["getRandomValues"];
}

declare global {
  interface Window {
    msCrypto?: MsCrypto;
  }
}

function isHtmlFormElement(target: EventTarget | null): target is HTMLFormElement {
  return target instanceof HTMLFormElement;
}

function csrfFieldFrom(formElement: HTMLFormElement): HTMLInputElement | null {
  return formElement.querySelector<HTMLInputElement>(
    'input[data-controller="csrf-protection"], input[name="_csrf_token"]',
  );
}

function randomToken(): string {
  const cryptoApi = window.crypto ?? window.msCrypto;
  if (!cryptoApi) {
    throw new Error("Secure random generator is unavailable.");
  }

  const bytes = cryptoApi.getRandomValues(new Uint8Array(18));
  return btoa(String.fromCharCode(...Array.from(bytes)));
}

// Generate and double-submit a CSRF token in a form field and a cookie, as defined by Symfony's SameOriginCsrfTokenManager.
document.addEventListener(
  "submit",
  (event: Event) => {
    if (isHtmlFormElement(event.target)) {
      generateCsrfToken(event.target);
    }
  },
  true,
);

export function generateCsrfToken(formElement: HTMLFormElement): void {
  const csrfField = csrfFieldFrom(formElement);
  if (!csrfField) {
    return;
  }

  let csrfCookie = csrfField.getAttribute("data-csrf-protection-cookie-value");
  let csrfToken = csrfField.value;

  if (!csrfCookie && nameCheck.test(csrfToken)) {
    csrfCookie = csrfToken;
    csrfField.setAttribute("data-csrf-protection-cookie-value", csrfCookie);
    csrfToken = randomToken();
    csrfField.defaultValue = csrfToken;
  }

  csrfField.dispatchEvent(new Event("change", { bubbles: true }));

  if (csrfCookie && tokenCheck.test(csrfToken)) {
    const cookie = `${csrfCookie}_${csrfToken}=${csrfCookie}; path=/; samesite=strict`;
    document.cookie = window.location.protocol === "https:" ? `__Host-${cookie}; secure` : cookie;
  }
}

export function removeCsrfToken(formElement: HTMLFormElement): void {
  const csrfField = csrfFieldFrom(formElement);
  if (!csrfField) {
    return;
  }

  const csrfCookie = csrfField.getAttribute("data-csrf-protection-cookie-value");
  if (csrfCookie && tokenCheck.test(csrfField.value) && nameCheck.test(csrfCookie)) {
    const cookie = `${csrfCookie}_${csrfField.value}=0; path=/; samesite=strict; max-age=0`;
    document.cookie = window.location.protocol === "https:" ? `__Host-${cookie}; secure` : cookie;
  }
}

/* stimulusFetch: 'lazy' */
export default "csrf-protection-controller";
