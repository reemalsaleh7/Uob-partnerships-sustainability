(function (global) {
    'use strict';

    function safeName(value) {
        return String(value || 'uob-record').trim()
            .replace(/[^A-Za-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '').slice(0, 90) || 'uob-record';
    }

    function downloadBlob(name, type, content) {
        const blob = new Blob([content], { type });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = name;
        document.body.append(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    function flatten(value, prefix = '', output = {}) {
        if (Array.isArray(value)) {
            if (value.every((item) => item == null || typeof item !== 'object')) {
                output[prefix] = value.join(' | ');
            } else {
                value.forEach((item, index) => {
                    flatten(item, `${prefix}${prefix ? '.' : ''}${index + 1}`, output);
                });
            }
        } else if (value && typeof value === 'object') {
            Object.entries(value).forEach(([key, item]) => {
                flatten(item, `${prefix}${prefix ? '.' : ''}${key}`, output);
            });
        } else if (prefix) {
            output[prefix] = value ?? '';
        }
        return output;
    }

    function csvCell(value) {
        return `"${String(value ?? '').replaceAll('"', '""')}"`;
    }

    function csv(value) {
        const row = flatten(value);
        const keys = Object.keys(row);
        return `\uFEFF${keys.map(csvCell).join(',')}\r\n${keys.map((key) => csvCell(row[key])).join(',')}\r\n`;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
        })[character]);
    }

    function printRecord(title, value) {
        const printable = global.open('', '_blank');
        if (!printable) throw new Error('Allow pop-ups to open the printable PDF view.');
        printable.opener = null;
        const rows = Object.entries(flatten(value))
            .filter(([, item]) => String(item ?? '').trim() !== '')
            .map(([key, item]) => `<tr><th>${escapeHtml(key.replaceAll('_', ' '))}</th><td>${escapeHtml(item)}</td></tr>`)
            .join('');
        printable.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${escapeHtml(title)}</title>
<style>body{font-family:Arial,sans-serif;color:#10233d;margin:36px}header{border-bottom:4px solid #c9a227;margin-bottom:24px;padding-bottom:14px}h1{font-size:24px;margin:0}p{color:#64748b}table{border-collapse:collapse;width:100%;font-size:12px}th,td{border:1px solid #dfe6ee;padding:8px;text-align:left;vertical-align:top;white-space:pre-wrap}th{background:#f3f6f9;width:32%;text-transform:capitalize}@media print{body{margin:12mm}tr{break-inside:avoid}}</style>
</head><body><header><h1>${escapeHtml(title)}</h1><p>University of Bahrain · Partnerships &amp; Sustainable Impact</p></header><table><tbody>${rows}</tbody></table><script>window.addEventListener('load',()=>window.print())<\/script></body></html>`);
        printable.document.close();
    }

    function download(baseName, value, format, title = 'UOB record') {
        const name = safeName(baseName);
        if (format === 'json') {
            downloadBlob(`${name}.json`, 'application/json;charset=utf-8', JSON.stringify(value, null, 2));
        } else if (format === 'csv') {
            downloadBlob(`${name}.csv`, 'text/csv;charset=utf-8', csv(value));
        } else if (format === 'pdf') {
            printRecord(title, value);
        } else {
            throw new Error('Choose PDF, CSV, or JSON.');
        }
    }

    global.WorkspaceExport = Object.freeze({ download });
})(window);
