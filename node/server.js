'use strict';

const express = require('express');
const puppeteer = require('puppeteer');
const bodyParser = require('body-parser');

// Constants
const PORT = 8080;
const HOST = '0.0.0.0';

// App
const app = express();
app.use(bodyParser.json()); // support json encoded bodies
app.use(bodyParser.urlencoded({extended: true})); // support encoded bodies

// app.get('/', (req, res) => {
//   res.send('Hello World');
// });

app.post('/puppeteer', async (req, res) => {
  console.log(req.body)
  const ricardoData = await run(req.body.url);
  ricardoData.puppeteerStatus === true ? res.status(200) : res.status(404);
  res.send(JSON.stringify(ricardoData));
  res.end();
});

async function run(url) {
  try {
    const browser = await puppeteer.launch({
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox'
      ]
    });
    const page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', (request) => {
      if (['image', 'stylesheet', 'font', 'script', 'other', 'xhr', 'text/plain', 'jpeg', 'gif'].indexOf(request.resourceType()) !== -1) {
        request.abort();
      }
      else {
        request.continue();
      }
    });
    let ricardoSite = await page.goto(url);
    let ricardo;
    if (ricardoSite && ricardoSite.status() === 200) {
      ricardo = await page.evaluate(() => window.ricardo);
      ricardo.puppeteerStatus = ricardo.initialState.pdp.article.id === '-1';
    }
    await browser.close();
    return ricardo;
  }
  catch (err) {
    console.error(err);
  }
}

app.listen(PORT, HOST);
console.log(`Running on http://${HOST}:${PORT}`);