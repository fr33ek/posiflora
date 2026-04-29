import { Navigate, Route, Routes } from 'react-router-dom'
import { TelegramGrowthPage } from './pages/TelegramGrowthPage'

function App() {
  return (
    <Routes>
      <Route path="/shops/:shopId/growth/telegram" element={<TelegramGrowthPage />} />
      <Route path="*" element={<Navigate to="/shops/1/growth/telegram" replace />} />
    </Routes>
  )
}

export default App
