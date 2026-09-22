// Génère docs/presentation/ecoworking-presentation.pdf depuis README.md
// (marked → HTML A4 imprimable → Chromium Playwright). Lancé dans le
// conteneur Sail par scripts/presentation/build.sh.
import { readFileSync, writeFileSync } from 'node:fs'
import { dirname, resolve } from 'node:path'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { chromium } from '@playwright/test'
import { marked } from 'marked'

const here = dirname(fileURLToPath(import.meta.url))
const docDir = resolve(here, '../../docs/presentation')
const markdown = readFileSync(resolve(docDir, 'README.md'), 'utf8')
  // Le lien vers le PDF n'a pas de sens dans le PDF lui-même.
  .replace(/^> Version PDF : .*\n/m, '')

const css = `
  @page { size: A4; margin: 16mm 14mm; }
  html { font: 10.5pt/1.45 -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; color: #1a1a1a; }
  h1 { font-size: 22pt; margin: 0 0 8pt; }
  h2 { font-size: 15pt; margin: 22pt 0 8pt; padding-bottom: 3pt; border-bottom: 1.5px solid #1a7f4b; page-break-after: avoid; }
  h3 { font-size: 12pt; margin: 16pt 0 5pt; page-break-after: avoid; }
  p, ul, ol { margin: 4pt 0; }
  li { margin: 1.5pt 0; }
  blockquote { margin: 0 0 10pt; padding: 6pt 10pt; border-left: 3px solid #1a7f4b; background: #f3faf6; }
  blockquote p { margin: 2pt 0; }
  code { font-family: "SFMono-Regular", Consolas, Menlo, monospace; font-size: 9pt; background: #f2f2f2; padding: 0 3px; border-radius: 3px; }
  pre { background: #f2f2f2; padding: 8pt; border-radius: 4px; font-size: 9pt; }
  table { border-collapse: collapse; width: 100%; margin: 6pt 0; font-size: 9.5pt; page-break-inside: avoid; }
  th, td { border: 1px solid #d8d8d8; padding: 4pt 6pt; vertical-align: top; text-align: left; }
  th { background: #f5f5f5; }
  img { max-width: 100%; max-height: 165mm; display: block; margin: 6pt auto 8pt; border: 1px solid #e2e2e2; border-radius: 4px; page-break-inside: avoid; }
  td img { max-height: 95mm; border: none; margin: 0; }
  hr { border: 0; border-top: 1px solid #ddd; margin: 14pt 0; }
  a { color: #1a7f4b; text-decoration: none; }
`

const html = `<!doctype html><html lang="fr"><head><meta charset="utf-8">
<title>Ecoworking — présentation de l'outil</title><style>${css}</style></head>
<body>${marked.parse(markdown)}</body></html>`

const htmlPath = resolve(docDir, '.presentation-print.html')
writeFileSync(htmlPath, html)

const browser = await chromium.launch()
try {
  const page = await browser.newPage()
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'networkidle' })
  await page.pdf({
    path: resolve(docDir, 'ecoworking-presentation.pdf'),
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: '<span></span>',
    footerTemplate:
      '<div style="width:100%;font-size:8pt;color:#888;text-align:center;font-family:sans-serif">Ecoworking — présentation de l\'outil · <span class="pageNumber"></span>/<span class="totalPages"></span></div>',
    margin: { top: '16mm', bottom: '16mm', left: '14mm', right: '14mm' },
  })
} finally {
  await browser.close()
}
console.log('PDF écrit : docs/presentation/ecoworking-presentation.pdf')
