/*
 * The filter form of a listing page, wherever it is drawn: in the
 * listing's own column or drawer, or in a Filters section of its own.
 *
 * The form is a plain GET form and works without this script. Where the
 * filters stand beside the products (`instant` says so), a ticked box or
 * a typed price is sent on its own; elsewhere (a drawer, or a closed box
 * on a phone) the shopper picks several and sends them with the form's
 * button. Empty price boxes are left out of the address.
 */
import { $$ } from '../util.js';

/**
 * @param {HTMLFormElement} form
 * @param {{ instant: () => boolean }} options
 * @returns {() => void} undoes everything this added
 */
export function initFilterForm(form, { instant }) {
  let timer = null;
  const sendIn = (delay) => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => form.requestSubmit(), delay);
  };

  const onChange = (event) => {
    if (instant() && event.target.matches('input[type=checkbox]')) sendIn(350);
  };
  const onInput = (event) => {
    if (instant() && event.target.type === 'number') sendIn(900);
  };
  const onSubmit = () => {
    window.clearTimeout(timer);
    // Empty price boxes would otherwise reach the address as min_price=.
    $$('input[type=number]', form).forEach((input) => { input.disabled = input.value.trim() === ''; });
  };

  form.addEventListener('change', onChange);
  form.addEventListener('input', onInput);
  form.addEventListener('submit', onSubmit);

  return () => {
    window.clearTimeout(timer);
    form.removeEventListener('change', onChange);
    form.removeEventListener('input', onInput);
    form.removeEventListener('submit', onSubmit);
  };
}
