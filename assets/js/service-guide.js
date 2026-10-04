(() => {
  'use strict';
  const close = document.getElementById('close-guide');
  if (close && window.opener) {
    close.hidden = false;
    close.addEventListener('click', () => window.close());
  }
})();
