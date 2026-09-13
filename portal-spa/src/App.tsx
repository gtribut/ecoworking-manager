import { Route, Routes } from 'react-router'
import { Layout } from '@/components/Layout'
import { NotFound } from '@/components/NotFound'
import { AnnouncementDetailPage } from '@/features/announcements/AnnouncementDetailPage'
import { AnnouncementsPage } from '@/features/announcements/AnnouncementsPage'
import { LoginPage } from '@/features/auth/LoginPage'
import { RequireAuth } from '@/features/auth/RequireAuth'
import { ResetPasswordPage } from '@/features/auth/ResetPasswordPage'
import { BookingsPage } from '@/features/bookings/BookingsPage'
import { DashboardPage } from '@/features/dashboard/DashboardPage'
import { DirectoryPage } from '@/features/directory/DirectoryPage'
import { FloorPlanPage } from '@/features/directory/FloorPlanPage'
import { DocumentsPage } from '@/features/documents/DocumentsPage'
import { InvoicesPage } from '@/features/invoices/InvoicesPage'
import { PresencePage } from '@/features/presence/PresencePage'
import { ProfilePage } from '@/features/profile/ProfilePage'
import { TicketsPage } from '@/features/tickets/TicketsPage'

export function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />
      {/* R-03 — cible du lien « mot de passe oublié » (PRD §3.2), hors auth */}
      <Route path="/reset-password/:token" element={<ResetPasswordPage />} />

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
        {/* C12.4 — Documents (internes à valider + administratifs) */}
        <Route path="documents" element={<DocumentsPage />} />
        <Route path="bookings" element={<BookingsPage />} />
        <Route path="announcements" element={<AnnouncementsPage />} />
        <Route path="announcements/:id" element={<AnnouncementDetailPage />} />
        <Route path="tickets" element={<TicketsPage />} />
        <Route path="presence" element={<PresencePage />} />
        {/* C12.5 — Annuaire des coworkers + plan des étages */}
        <Route path="directory" element={<DirectoryPage />} />
        <Route path="directory/plan" element={<FloorPlanPage />} />
      </Route>

      <Route path="*" element={<NotFound />} />
    </Routes>
  )
}
