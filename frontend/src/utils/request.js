import axios from 'axios'
import { ElMessage } from 'element-plus'

const service = axios.create({
  baseURL: '/api',
  timeout: 10000
})

service.interceptors.response.use(
  res => {
    const d = res.data
    if (d.code !== 200) {
      ElMessage.error(d.msg || '操作失败')
      return Promise.reject(d)
    }
    return d
  },
  err => {
    ElMessage.error('网络异常')
    return Promise.reject(err)
  }
)

export default service
