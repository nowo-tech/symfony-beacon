import './styles/tailwind.css';
import './styles/app.scss';

document.documentElement.dataset.assets = 'ts+scss+tailwind';

// Close locale dropdown when clicking outside (details/summary).
document.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof Node)) {
    return;
  }

  document.querySelectorAll('details.locale-switcher__details[open]').forEach((details) => {
    if (!details.contains(target)) {
      details.removeAttribute('open');
    }
  });
});

export {};
