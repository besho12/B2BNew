import { computed } from 'vue'
import { useAuthStore } from './stores/auth'

const messages: Record<'en' | 'ar', Record<string, string>> = {
  en: {
    dashboard: 'Dashboard', leads: 'Leads', tasks: 'Tasks', support: 'Support', inventory: 'Inventory', deals: 'Deals', quickOrder: 'Quick order',
    commissions: 'Commissions', reports: 'Reports', locations: 'Locations', developers: 'Developers', company: 'Company profile', team: 'Team', orders: 'Order history', account: 'Customer account', myAccount: 'My account', addresses: 'Address book', bpUsers: 'BP users', search: 'Search',
    newLead: 'New lead', overview: 'Overview', welcome: 'Good to see you', signOut: 'Sign out',
  },
  ar: {
    orders: 'Order history',
    account: 'Customer account',
    myAccount: 'حسابي',
    quickOrder: 'طلب سريع',
    addresses: 'Address book',
    bpUsers: 'مستخدمو حساب الأعمال',
    developers: 'المطورون العقاريون', company: 'ملف الشركة',
    dashboard: 'لوحة التحكم', leads: 'العملاء', tasks: 'المهام', support: 'الدعم', inventory: 'العقارات', deals: 'الصفقات',
    commissions: 'العمولات', reports: 'التقارير', locations: 'المناطق', team: 'الفريق', search: 'بحث',
    newLead: 'عميل جديد', overview: 'نظرة عامة', welcome: 'أهلاً بعودتك', signOut: 'تسجيل الخروج',
  },
} as const

export function useI18n() {
  const auth = useAuthStore()
  const t = computed(() => messages[auth.locale])
  return { t }
}
