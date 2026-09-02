const fs = require('fs');
const path = require('path');
const assert = require('assert/strict');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
(async () => {
 const browser = await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL || 'chrome'});
 try {
  const page = await browser.newPage();
  let requests=[],delay=0;
  const html = (query='') => `<html><body><form id="reporting-filter-form" action="http://strax.test/report"><select name="client_id"><option value="">Tous</option><option value="1">Client</option></select><select id="reporting-page" name="connection_id"><option value="">Toutes</option><option value="4" selected>Page</option></select><input name="sort" value="date_publication"><input name="direction" value="desc"><button>Filtrer</button></form><div id="report-export-actions"><a href="http://strax.test/report?export=pdf&report_type=individual">PDF</a></div><div id="reporting-results"><p id="query">${query.replace(/&/g,'&amp;')}</p><details class="report-columns"><summary>Colonnes</summary><input type="checkbox" checked form="reporting-filter-form" name="columns[individual][]" value="vues"></details><button class="report-sort" data-sort="vues">Vues</button></div></body></html>`;
  await page.route('http://strax.test/**',async route=>{
    requests.push(new URL(route.request().url()).searchParams);
    if(delay) await new Promise(resolve=>setTimeout(resolve,delay));
    await route.fulfill({contentType:'text/html',body:html(new URL(route.request().url()).search)});
  });
  await page.goto('http://strax.test/report');
  await page.addScriptTag({content:fs.readFileSync(path.join(__dirname,'../public/assets/reporting-workspace.js'),'utf8')});
  await page.selectOption('[name=client_id]','1');
  await page.waitForFunction(()=>document.getElementById('query').textContent.includes('client_id=1'));
  assert.equal(requests.at(-1).get('connection_id'),'');
  await page.click('.report-sort');
  await page.waitForFunction(()=>document.getElementById('query').textContent.includes('sort=vues'));
  await page.click('.report-sort');
  await page.waitForFunction(()=>document.getElementById('query').textContent.includes('direction=asc'));
  assert.equal(requests.at(-1).get('columns[individual][]'),'vues');
  delay=850;
  await page.click('form button');
  await page.waitForTimeout(100);
  assert.equal(await page.locator('.report-status').textContent(),'');
  await page.waitForFunction(()=>document.querySelector('.report-status').classList.contains('busy'));
  await page.waitForFunction(()=>!document.querySelector('.report-status').classList.contains('busy'));
  console.log('OK: AJAX filters, page reset, sorting, selected KPI and delayed loader');
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
