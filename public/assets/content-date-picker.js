document.addEventListener('click', event => {
  const day=event.target.closest('[data-select-content-date]');
  if(!day)return;
  event.preventDefault();
  const form=day.closest('form');
  const field=form?.querySelector('[name="date_prevue"]');
  if(!field||field.disabled)return;
  field.value=day.dataset.selectContentDate;
  field.dispatchEvent(new Event('input',{bubbles:true}));
  field.dispatchEvent(new Event('change',{bubbles:true}));
  day.closest('details').open=false;
  field.focus({preventScroll:true});
});
