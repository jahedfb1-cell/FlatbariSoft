/**
 * One tap, one entry. A second submit of the same form within a moment (a double tap on a phone, an impatient click while
 * the server is still working) is dropped before it reaches the page's own handler - so a payment, an expense or a
 * purchase can never be entered twice by accident. It is installed once for every form in the app.
 */
const WINDOW_MS = 2000;
const lastSent = new WeakMap<EventTarget, number>();

export function installFormGuard(): void {
  document.addEventListener(
    'submit',
    (e) => {
      const form = e.target;
      if (!form) return;
      const now = Date.now();
      const last = lastSent.get(form) ?? 0;
      if (now - last < WINDOW_MS) {
        e.preventDefault();
        e.stopPropagation(); // React hears events on the root, below the document: it never sees this one
        return;
      }
      lastSent.set(form, now);
    },
    true,
  );
}
