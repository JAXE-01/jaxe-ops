<?php $socialPages=ProjectSocialPages::available(); $selectedPages=isset($_POST['social_pages_present'])?array_map('intval',(array)($_POST['social_page_ids']??[])):ProjectSocialPages::selected((int)($record['id']??0)); ?>
<fieldset class="project-social-pages" style="grid-column:1/-1;min-width:0">
    <legend>Pages et comptes de ce projet</legend>
    <p>Seules les pages connectées du client choisi sont proposées. Cochez celles autorisées à publier pour ce projet.</p>
    <input type="hidden" name="social_pages_present" value="1">
    <?php foreach($socialPages as $page): ?>
    <label data-social-client="<?= (int)$page['client_id'] ?>" style="padding:8px">
        <input style="width:auto" type="checkbox" name="social_page_ids[]" value="<?= (int)$page['id'] ?>" <?= in_array((int)$page['id'],$selectedPages,true)?'checked':'' ?>>
        <?= htmlspecialchars($page['account_label'].' · '.ucfirst($page['provider']),ENT_QUOTES,'UTF-8') ?>
    </label>
    <?php endforeach ?>
    <p data-social-pages-empty>Aucune page connectée pour ce client. Connectez Meta depuis Publication sociale.</p>
</fieldset>
<script>
(function(){
 const box=document.querySelector('.project-social-pages'),client=box.closest('form').querySelector('[name="client_id"]');
 function refresh(){let count=0;box.querySelectorAll('[data-social-client]').forEach(row=>{const show=!!client?.value&&row.dataset.socialClient===client.value;row.style.display=show?'flex':'none';const input=row.querySelector('input');input.disabled=!show;if(!show)input.checked=false;if(show)count++;});box.querySelector('[data-social-pages-empty]').hidden=count>0;}
 client?.addEventListener('change',refresh);refresh();
})();
</script>
