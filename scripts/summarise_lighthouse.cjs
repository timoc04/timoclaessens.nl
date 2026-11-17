// Print Lighthouse scores and core metrics to the GitHub Actions summary.
const fs = require('node:fs');
const path = require('node:path');

const categories = ['performance', 'accessibility', 'best-practices', 'seo'];
const metrics = [
  ['largest-contentful-paint', 'LCP'],
  ['cumulative-layout-shift', 'CLS'],
  ['total-blocking-time', 'TBT'],
  ['first-contentful-paint', 'FCP']
];
let markdown = '# Live Lighthouse audit\n\n';
let reports = 0;
for (const mode of ['mobile', 'desktop']) {
  const file = path.join('lighthouse-reports', mode + '.json');
  if (!fs.existsSync(file)) {
    markdown += '## ' + mode + '\n\nReport unavailable (audit may have failed).\n\n';
    continue;
  }
  const data = JSON.parse(fs.readFileSync(file, 'utf8'));
  reports++;
  markdown += '## ' + mode + '\n\n';
  markdown += 'Audited URL: ' + data.finalUrl + '\n\n';
  markdown += '| Category | Score |\n|---|---:|\n';
  for (const key of categories) {
    const category = data.categories?.[key];
    markdown += '| ' + (category?.title || key) + ' | ' +
      (typeof category?.score === 'number' ? Math.round(category.score * 100) + '/100' : 'N/A') + ' |\n';
  }
  markdown += '\n| Metric | Value |\n|---|---|\n';
  for (const [key, label] of metrics) {
    markdown += '| ' + label + ' | ' + (data.audits?.[key]?.displayValue || 'N/A') + ' |\n';
  }
  markdown += '\nScores are synthetic lab measurements, not real-user Core Web Vitals.\n\n';
}
process.stdout.write(markdown);
if (process.env.GITHUB_STEP_SUMMARY) {
  fs.appendFileSync(process.env.GITHUB_STEP_SUMMARY, markdown);
}
if (reports === 0) process.exitCode = 1;
