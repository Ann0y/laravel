// 统一 API 封装：走 /api 前缀（vite dev 代理到 Laravel 8000，生产反代同域），
// 后端统一响应体 {code, message, data}，code=200 为成功；Session 由 Cookie 自动携带。
export async function api(path, { method = 'GET', body } = {}) {
  const res = await fetch('/api' + path, {
    method,
    headers: body ? { 'Content-Type': 'application/json' } : undefined,
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin',
  })

  let payload
  try {
    payload = await res.json()
  } catch {
    throw new Error('服务暂时不可用，请稍后重试')
  }

  if (payload.code !== 200) {
    const err = new Error(payload.message || '请求失败')
    err.status = payload.code
    throw err
  }
  return payload.data
}
