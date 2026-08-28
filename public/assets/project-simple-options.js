(()=>{
 const campaign=document.querySelector('.crud-entity-form [name="campagne_id"]');if(!campaign)return;
 const field=campaign.closest('label');if(!field)return;
 const details=document.createElement('details');details.className='panel field-wide';details.open=Boolean(campaign.value);
 const summary=document.createElement('summary');summary.textContent='Stratégie avancée — campagne complémentaire';
 const text=document.createElement('p');text.textContent='Le projet suffit pour organiser votre calendrier. Associez une campagne seulement pour une opération marketing distincte ; ce champ reste facultatif.';
 field.before(details);details.append(summary,text,field);
})();
