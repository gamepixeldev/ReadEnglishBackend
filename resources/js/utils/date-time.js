export function formatBeijingDateTime(value) {
  if (!value) return ''
  let source = String(value).trim().replace(' ', 'T')
  source = source.replace(/\.(\d{3})\d+(?=Z|[+-]\d{2}:?\d{2}$)/i, '.$1')
  source = source.replace(/([+-]\d{2})(\d{2})$/, '$1:$2')
  if (!/(?:Z|[+-]\d{2}:\d{2})$/i.test(source)) source += 'Z'
  const parsed = new Date(source)
  if (Number.isNaN(parsed.getTime())) return ''
  return new Intl.DateTimeFormat('zh-CN', {
    timeZone: 'Asia/Shanghai',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23'
  }).format(parsed)
}
