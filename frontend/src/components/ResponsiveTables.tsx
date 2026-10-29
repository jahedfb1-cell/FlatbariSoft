import { useEffect } from 'react';

/**
 * On a phone a table cannot stay a table: it either scrolls sideways (data hidden off-screen) or squeezes
 * until nothing fits. index.css turns every table into one card per row below 640px, and each cell shows
 * its column heading above its value. CSS cannot read the heading, so this copies it onto every cell as
 * data-label - and keeps doing so when a page loads or changes its rows. A table that must stay a real
 * table (wide statements, print layouts) opts out with the class `no-stack`.
 */
function label(table: HTMLTableElement) {
  if (table.classList.contains('no-stack')) return;
  const head = table.tHead?.rows[table.tHead.rows.length - 1];
  if (!head) return;

  // column index -> heading text, honouring colspan in the heading row
  const names: string[] = [];
  Array.from(head.cells).forEach((th) => {
    const text = (th.textContent ?? '').trim();
    for (let i = 0; i < Math.max(1, th.colSpan); i++) names.push(text);
  });

  Array.from(table.rows).forEach((row) => {
    if (row.parentElement === table.tHead) return;
    let col = 0;
    Array.from(row.cells).forEach((cell) => {
      const name = names[col] ?? '';
      if (cell.getAttribute('data-label') !== name) cell.setAttribute('data-label', name);
      col += Math.max(1, cell.colSpan);
    });
  });
}

export default function ResponsiveTables() {
  useEffect(() => {
    let frame = 0;
    const run = () => {
      frame = 0;
      document.querySelectorAll<HTMLTableElement>('table').forEach(label);
    };
    const schedule = () => {
      if (!frame) frame = requestAnimationFrame(run);
    };
    schedule();
    const observer = new MutationObserver(schedule);
    observer.observe(document.body, { childList: true, subtree: true, characterData: true });
    return () => {
      observer.disconnect();
      if (frame) cancelAnimationFrame(frame);
    };
  }, []);

  return null;
}
