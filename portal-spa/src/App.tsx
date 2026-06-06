import { Route, Routes } from 'react-router'
import { Layout } from '@/components/Layout'
import { NotFound } from '@/components/NotFound'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { InvoicesPage } from '@/features/invoices/InvoicesPage'
import { ProfilePage } from '@/features/profile/ProfilePage'

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      <Route
        element={
          <RequireAuth>
            <Layout />
          </RequireAuth>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="profile" element={<ProfilePage />} />
        <Route path="invoices" element={<InvoicesPage />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}
