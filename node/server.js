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

app.post('/health-check', async(req, res) => {
  console.log(req.body)
  const healthy = await runHealthCheck();
  healthy && healthy === true ? res.status(200) : res.status(404);
  res.send(JSON.stringify(ricardoData));
  res.end();
});

app.post('/puppeteer', async(req, res) => {
  console.log(req.body)
  const ricardoData = await run(req.body.url);
  ricardoData && ricardoData.puppeteerStatus === true ? res.status(200) : res.status(404);
  res.send(JSON.stringify(ricardoData));
  res.end();
});

app.post('/puppeteer-seller', async(req, res) => {
  console.log(req.body)
  const ricardoData = await runSeller(req.body.url);
  ricardoData && ricardoData.puppeteerStatus === true ? res.status(200) : res.status(404);
  console.log(ricardoData)
  res.send(JSON.stringify(ricardoData));
  res.end();
});

async function runHealthCheck() {
  try {
    const browser = await puppeteer.launch({
      executablePath: '/usr/bin/chromium-browser',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox'
      ]
    });
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.140 Safari/537.36 Edge/18.17763');
    await page.setRequestInterception(true);
    page.on('request', (request) => {
      if (['image', 'stylesheet', 'font', 'script', 'other', 'xhr', 'text/plain', 'jpeg', 'gif'].indexOf(request.resourceType()) !== -1) {
        request.abort();
      }
      else {
        request.continue();
      }
    });
    let healthy = false;
    let ricardoSite = await page.goto('https://ricardo.ch');
    if (ricardoSite.status() === 200) {
      healthy = true;
    }
    await browser.close();
    return healthy;
  }
  catch (err) {
    console.error(err);
  }
}

async function runSeller(url) {
  try {
    const browser = await puppeteer.launch({
      executablePath: '/usr/bin/chromium-browser',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox'
      ]
    });
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.140 Safari/537.36 Edge/18.17763');
    await page.setRequestInterception(true);
    page.on('request', (request) => {
      if (['image', 'stylesheet', 'font', 'script', 'other', 'xhr', 'text/plain', 'jpeg', 'gif'].indexOf(request.resourceType()) !== -1) {
        request.abort();
      }
      else {
        request.continue();
      }
    });
    let ricardo;
    let ricardoSite = await page.goto(url);
    if (ricardoSite) {
      ricardo = await page.evaluate(() => window.ricardo);
      ricardo.puppeteerStatus = ricardo ? true : false;
    }
    await browser.close();
    return ricardo;
  }
  catch (err) {
    console.error(err);
  }
}


async function run(url) {
  try {
    const browser = await puppeteer.launch({
      executablePath: '/usr/bin/chromium-browser',
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox'
      ]
    });
    const page = await browser.newPage();
    await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/64.0.3282.140 Safari/537.36 Edge/18.17763');
    await page.setRequestInterception(true);
    page.on('request', (request) => {
      if (['image', 'stylesheet', 'font', 'script', 'other', 'xhr', 'text/plain', 'jpeg', 'gif'].indexOf(request.resourceType()) !== -1) {
        request.abort();
      }
      else {
        request.continue();
      }
    });
    let ricardo;
    let ricardoSite = await page.goto(url);
    if (ricardoSite) {
      ricardo = await page.evaluate(() => window.ricardo);
      ricardo.puppeteerStatus = ricardo.initialState.pdp.article.id === '-1' ? false : true;
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
