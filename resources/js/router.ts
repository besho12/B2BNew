import { createRouter, createWebHistory } from 'vue-router'
import LoginView from './views/LoginView.vue'
import RegisterView from './views/RegisterView.vue'
import DashboardView from './views/DashboardView.vue'
import LeadsView from './views/LeadsView.vue'
import LeadDetailView from './views/LeadDetailView.vue'
import TasksView from './views/TasksView.vue'
import ComingSoonView from './views/ComingSoonView.vue'
import PreferredLocationsView from './views/PreferredLocationsView.vue'
import AcceptInvitationView from './views/AcceptInvitationView.vue'
import ForgotPasswordView from './views/ForgotPasswordView.vue'
import ResetPasswordView from './views/ResetPasswordView.vue'
import VerifyEmailView from './views/VerifyEmailView.vue'
import InventoryView from './views/InventoryView.vue'
import DealsView from './views/DealsView.vue'
import ReportsView from './views/ReportsView.vue'
import CommissionsView from './views/CommissionsView.vue'
import CommissionOrderView from './views/CommissionOrderView.vue'
import DealClosingDocumentView from './views/DealClosingDocumentView.vue'
import LandingView from './views/LandingView.vue'
import RealEstateDevelopersView from './views/RealEstateDevelopersView.vue'
import CompanyProfileView from './views/CompanyProfileView.vue'
import B2BAccountSelectView from './views/B2BAccountSelectView.vue'
import B2BProductsView from './views/B2BProductsView.vue'
import B2BCartView from './views/B2BCartView.vue'
import B2BQuickOrderView from './views/B2BQuickOrderView.vue'
import B2BOrdersView from './views/B2BOrdersView.vue'
import B2BAccountDetailsView from './views/B2BAccountDetailsView.vue'
import MyAccountView from './views/MyAccountView.vue'
import B2BAddressesView from './views/B2BAddressesView.vue'
import BPUsersView from './views/BPUsersView.vue'
import GlobalUsersView from './views/GlobalUsersView.vue'
import PortalOwnerSettingsView from './views/PortalOwnerSettingsView.vue'
import PortalMailTemplatesView from './views/PortalMailTemplatesView.vue'
import PlatformDashboardView from './views/PlatformDashboardView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', component: LoginView, meta: { guest: true } },
    { path: '/register', component: RegisterView, meta: { guest: true } },
    { path: '/accept-invite', component: AcceptInvitationView, meta: { guest: true } },
    { path: '/forgot-password', component: ForgotPasswordView, meta: { guest: true, allowAuthenticated: true } },
    { path: '/reset-password', component: ResetPasswordView, meta: { guest: true, allowAuthenticated: true } },
    { path: '/verify-email', component: VerifyEmailView, meta: { guest: true, allowAuthenticated: true } },
    { path: '/select-account', component: B2BAccountSelectView, meta: { guest: true, allowAuthenticated: true } },
    { path: '/', component: LandingView, meta: { public: true } },
    { path: '/home2', redirect: '/' },
    { path: '/home3', redirect: '/' },
    { path: '/plans', redirect: '/' },
    { path: '/billing', redirect: '/' },
    { path: '/platform', component: PlatformDashboardView },
    { path: '/platform/settings', component: PortalOwnerSettingsView },
    { path: '/platform/mail-templates', component: PortalMailTemplatesView },
    { path: '/dashboard', component: DashboardView },
    { path: '/leads', component: LeadsView },
    { path: '/leads/:id', component: LeadDetailView },
    { path: '/tasks', component: TasksView },
    { path: '/products', component: B2BProductsView },
    { path: '/cart', component: B2BCartView },
    { path: '/user/quickOrder', component: B2BQuickOrderView },
    { path: '/orders', component: B2BOrdersView },
    { path: '/account', component: B2BAccountDetailsView },
    { path: '/user/my_account', component: MyAccountView },
    { path: '/addresses', component: B2BAddressesView },
    { path: '/inventory', component: InventoryView },
    { path: '/deals', component: DealsView },
    { path: '/deals/:id/closing-document', component: DealClosingDocumentView },
    { path: '/commissions', component: CommissionsView },
    { path: '/commissions/:id/order', component: CommissionOrderView },
    { path: '/reports', component: ReportsView },
    { path: '/settings/locations', component: PreferredLocationsView },
    { path: '/settings/developers', component: RealEstateDevelopersView },
    { path: '/settings/company', component: CompanyProfileView },
    { path: '/users/bp', component: BPUsersView },
    { path: '/users/global', component: GlobalUsersView },
    { path: '/:section', component: ComingSoonView },
  ],
})

export default router
