(() => {
  const restoreScreenState = () => {
    document.documentElement.classList.remove('aprism-print-active');
  };

  window.addEventListener('afterprint', restoreScreenState);
  if (typeof window.matchMedia === 'function') {
    const printMedia = window.matchMedia('print');
    const handlePrintMediaChange = (event) => {
      if (!event.matches) restoreScreenState();
    };
    if (typeof printMedia.addEventListener === 'function') {
      printMedia.addEventListener('change', handlePrintMediaChange);
    } else if (typeof printMedia.addListener === 'function') {
      printMedia.addListener(handlePrintMediaChange);
    }
  }

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof Element)) return;
    const trigger = target.closest('[data-report-print]');
    if (!trigger || trigger.hasAttribute('disabled')) return;
    event.preventDefault();
    document.documentElement.classList.add('aprism-print-active');
    window.requestAnimationFrame(() => {
      try {
        window.print();
      } catch {
        restoreScreenState();
      }
    });
  });
})();