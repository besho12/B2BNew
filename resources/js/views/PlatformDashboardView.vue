<script setup lang="ts">
import { ArrowRight, ArrowUpRight, Building2, CheckCircle2, Clock3, PackageSearch, ShoppingCart, Sparkles, TrendingUp, Users } from '@lucide/vue'
import PortalOwnerLayout from '../components/PortalOwnerLayout.vue'

const metrics = [
  { label: 'Customer accounts', value: '1,284', change: '+8.2%', caption: 'vs. previous month', icon: Building2, tone: 'gold' },
  { label: 'Portal users', value: '3,672', change: '+12.4%', caption: 'across all accounts', icon: Users, tone: 'green' },
  { label: 'Orders this month', value: '8,419', change: '+6.8%', caption: 'submitted through portal', icon: ShoppingCart, tone: 'blue' },
  { label: 'Active products', value: '2,356', change: '+3.1%', caption: 'available in catalogue', icon: PackageSearch, tone: 'violet' },
]
const orderBars = [
  { day: 'Mon', value: 62 }, { day: 'Tue', value: 78 }, { day: 'Wed', value: 55 }, { day: 'Thu', value: 91 },
  { day: 'Fri', value: 72 }, { day: 'Sat', value: 44 }, { day: 'Sun', value: 33 },
]
const orderStates = [
  { name: 'Submitted', count: '1,284', percent: 72, tone: 'green' },
  { name: 'Processing', count: '386', percent: 45, tone: 'gold' },
  { name: 'Awaiting dispatch', count: '142', percent: 27, tone: 'blue' },
]
const topProducts = [
  { name: 'Hydro Clean Concentrate', sku: 'SAMPLE-1042', orders: 824, share: 84 },
  { name: 'Workshop Brush Set', sku: 'SAMPLE-2078', orders: 612, share: 63 },
  { name: 'Protective Chain Lube', sku: 'SAMPLE-3150', orders: 458, share: 48 },
  { name: 'Premium Microfibre Cloth', sku: 'SAMPLE-4221', orders: 391, share: 40 },
]
const activity = [
  { title: 'A sample account placed an order', detail: 'Cairo Cycle Supply · Order EX-10482', time: '12 min ago', type: 'order' },
  { title: 'A sample user joined the portal', detail: 'Alex Morgan · Riverside Bikes', time: '46 min ago', type: 'user' },
  { title: 'A sample order was dispatched', detail: 'Northstar Workshop · Order EX-10476', time: '1 hr ago', type: 'complete' },
]
const number = (value: number) => new Intl.NumberFormat('en-EG').format(value)
</script>

<template>
  <PortalOwnerLayout>
    <main class="portal-dashboard">
      <header class="dashboard-heading">
        <div><span class="dashboard-eyebrow">PORTAL OVERVIEW</span><h1>Your portal, <em>at a glance.</em></h1><p>Activity across customer accounts, users, orders, and products.</p></div>
        <span class="sample-badge"><Sparkles :size="14"/> SAMPLE DATA</span>
      </header>

      <aside class="sample-notice" aria-label="Sample data notice"><Sparkles :size="16"/><p><strong>Dashboard preview</strong><span>These fictional figures show the planned portal summary. They will be replaced with live totals when the reporting endpoints are available.</span></p></aside>

      <section class="dashboard-metrics" aria-label="Sample portal totals">
        <article v-for="metric in metrics" :key="metric.label" class="metric-card">
          <span class="metric-icon" :class="metric.tone"><component :is="metric.icon" :size="18"/></span>
          <small>{{ metric.label }}</small><strong>{{ metric.value }}</strong>
          <span class="metric-change"><TrendingUp :size="13"/>{{ metric.change }} <span>{{ metric.caption }}</span></span>
        </article>
      </section>

      <section class="dashboard-grid">
        <article class="dashboard-panel order-trend">
          <header><div><span class="dashboard-eyebrow">SAMPLE ORDER VOLUME</span><h2>Orders through the portal</h2><p>Illustrative activity for the last seven days</p></div><button class="period-select" type="button" disabled>This week <span>⌄</span></button></header>
          <div class="trend-summary"><strong>2,846</strong><span><i><TrendingUp :size="13"/> 6.8%</i> from last week</span></div>
          <div class="bar-chart" role="img" aria-label="Sample daily order volumes: Monday 62 percent, Tuesday 78 percent, Wednesday 55 percent, Thursday 91 percent, Friday 72 percent, Saturday 44 percent, Sunday 33 percent">
            <div class="chart-grid"><i></i><i></i><i></i><i></i></div>
            <div v-for="bar in orderBars" :key="bar.day" class="bar-column"><span class="bar-value">{{ Math.round(bar.value * 31) }}</span><i :style="{ height: `${bar.value}%` }"></i><small>{{ bar.day }}</small></div>
          </div>
        </article>

        <article class="dashboard-panel order-status">
          <header><div><span class="dashboard-eyebrow">SAMPLE FULFILMENT</span><h2>Order status</h2><p>Current month · fictional breakdown</p></div><ShoppingCart :size="18"/></header>
          <div v-for="state in orderStates" :key="state.name" class="status-row"><div><span>{{ state.name }}</span><strong>{{ state.count }}</strong></div><i><b :class="state.tone" :style="{ width: `${state.percent}%` }"></b></i></div>
          <div class="status-footer"><span><Clock3 :size="14"/> 142 awaiting dispatch</span><span>Sample</span></div>
        </article>

        <article class="dashboard-panel product-panel">
          <header><div><span class="dashboard-eyebrow">SAMPLE CATALOGUE</span><h2>Popular products</h2><p>Top items by sample order activity</p></div><PackageSearch :size="18"/></header>
          <div v-for="(product, index) in topProducts" :key="product.sku" class="product-row"><span class="product-rank">0{{ index + 1 }}</span><span class="product-info"><strong>{{ product.name }}</strong><small>{{ product.sku }}</small><i><b :style="{ width: `${product.share}%` }"></b></i></span><span class="product-orders">{{ number(product.orders) }}<small>orders</small></span></div>
        </article>

        <article class="dashboard-panel activity-panel">
          <header><div><span class="dashboard-eyebrow">SAMPLE ACTIVITY</span><h2>Recent portal activity</h2><p>Example events from a future live activity feed</p></div><span class="activity-dot"><i></i> PREVIEW</span></header>
          <div v-for="item in activity" :key="item.detail" class="activity-row"><span class="activity-icon" :class="item.type"><ShoppingCart v-if="item.type==='order'" :size="15"/><Users v-else-if="item.type==='user'" :size="15"/><CheckCircle2 v-else :size="15"/></span><div><strong>{{ item.title }}</strong><small>{{ item.detail }}</small></div><time>{{ item.time }}</time></div>
          <RouterLink to="/users/global" class="activity-link">Manage portal access <ArrowRight :size="14"/></RouterLink>
        </article>
      </section>
      <footer class="dashboard-footnote"><span>All names, order references, quantities, and trend figures on this screen are fictional sample data.</span><span>Portal reporting preview <ArrowUpRight :size="13"/></span></footer>
    </main>
  </PortalOwnerLayout>
</template>

<style scoped>
.portal-dashboard{max-width:1440px;margin:0 auto;color:#203630}.dashboard-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin:0 0 22px}.dashboard-eyebrow{color:#9a7641;font-size:9px;font-weight:800;letter-spacing:.14em}.dashboard-heading h1{margin:7px 0 6px;color:#19352f;font-size:clamp(27px,3vw,38px);font-weight:500;letter-spacing:-.045em}.dashboard-heading h1 em{color:#a4824c;font-family:Georgia,serif;font-weight:400}.dashboard-heading p{margin:0;color:#78877f;font-size:12px}.sample-badge{display:inline-flex;align-items:center;gap:7px;padding:8px 11px;border:1px solid #eadfca;border-radius:30px;background:#fffaf0;color:#8a6b35;font-size:8px;font-weight:800;letter-spacing:.1em;white-space:nowrap}.sample-notice{display:flex;align-items:flex-start;gap:11px;margin-bottom:17px;padding:13px 15px;border:1px solid #eadfca;border-radius:10px;background:#fffaf1;color:#8a6b35}.sample-notice>svg{flex:none;margin-top:1px}.sample-notice p{display:grid;gap:3px;margin:0}.sample-notice strong{font-size:10px}.sample-notice span{color:#83775f;font-size:10px;line-height:1.55}.dashboard-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px;margin-bottom:14px}.metric-card,.dashboard-panel{border:1px solid #e4e9e3;border-radius:11px;background:#fff;box-shadow:0 3px 12px #18392b08}.metric-card{min-height:150px;padding:16px}.metric-icon{width:34px;height:34px;display:grid;place-items:center;margin-bottom:13px;border-radius:9px}.metric-icon.gold{background:#f8f0e1;color:#a17a37}.metric-icon.green{background:#e9f3ec;color:#4e8261}.metric-icon.blue{background:#eaf1f7;color:#527d9c}.metric-icon.violet{background:#f0ecf7;color:#786599}.metric-card>small{display:block;color:#79867f;font-size:9px}.metric-card>strong{display:block;margin-top:3px;color:#243c33;font-size:26px;font-weight:600;letter-spacing:-.04em}.metric-change{display:flex;align-items:center;gap:4px;margin-top:5px;color:#50805e;font-size:9px;font-weight:700}.metric-change span{overflow:hidden;color:#8a9690;font-weight:400;text-overflow:ellipsis;white-space:nowrap}.dashboard-grid{display:grid;grid-template-columns:1.35fr 1fr;gap:14px}.dashboard-panel{min-width:0;padding:17px}.dashboard-panel>header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px}.dashboard-panel>header>svg{color:#809187}.dashboard-panel h2{margin:5px 0;color:#2c4238;font-size:14px;font-weight:600}.dashboard-panel header p{margin:0;color:#87938d;font-size:9px}.period-select{padding:7px 9px;border:1px solid #e6ebe5;border-radius:7px;background:#fafbf9;color:#7e8c83;font-size:9px}.period-select span{margin-left:8px}.trend-summary{display:flex;align-items:baseline;gap:9px;margin:18px 0 2px}.trend-summary>strong{color:#233d33;font-size:25px}.trend-summary>span{color:#8a9690;font-size:9px}.trend-summary i{display:inline-flex;align-items:center;gap:3px;color:#54815f;font-style:normal;font-weight:700}.bar-chart{position:relative;height:150px;display:flex;align-items:flex-end;justify-content:space-around;gap:10px;padding:8px 6px 22px}.chart-grid{position:absolute;inset:10px 0 28px;display:flex;flex-direction:column;justify-content:space-between}.chart-grid i{border-top:1px dashed #e8ede8}.bar-column{position:relative;z-index:1;width:8%;height:100%;display:flex;align-items:center;justify-content:flex-end;flex-direction:column;gap:6px}.bar-column>i{width:min(32px,80%);min-height:12px;border-radius:5px 5px 2px 2px;background:linear-gradient(180deg,#c9b07c,#78917d)}.bar-column:nth-of-type(2)>i,.bar-column:nth-of-type(5)>i{background:linear-gradient(180deg,#deca9e,#b89456)}.bar-column small{position:absolute;bottom:-19px;color:#8b9690;font-size:8px}.bar-value{color:#87938d;font-size:8px}.order-status>header>svg,.product-panel>header>svg{margin:2px;color:#698272}.status-row{margin-top:19px}.status-row>div{display:flex;justify-content:space-between;margin-bottom:7px;color:#728078;font-size:9px}.status-row strong{color:#30463b;font-size:10px}.status-row>i,.product-info>i{height:6px;display:block;overflow:hidden;border-radius:10px;background:#eff2ee}.status-row>i>b,.product-info>i>b{height:100%;display:block;border-radius:10px}.status-row b.green{background:#7ea68a}.status-row b.gold{background:#c8a765}.status-row b.blue{background:#8aa8bd}.status-footer{display:flex;align-items:center;justify-content:space-between;margin-top:20px;padding-top:12px;border-top:1px solid #edf0ec;color:#738178;font-size:9px}.status-footer>span:first-child{display:flex;align-items:center;gap:6px}.status-footer svg{color:#b38c4d}.status-footer>span:last-child{color:#a18452;font-size:8px;font-weight:800;letter-spacing:.1em}.product-panel,.activity-panel{min-height:255px}.product-row{display:grid;grid-template-columns:24px minmax(0,1fr) auto;align-items:center;gap:10px;margin-top:15px}.product-rank{color:#b19a6b;font-size:9px;font-weight:700}.product-info{min-width:0;display:grid;gap:4px}.product-info strong{overflow:hidden;color:#36483f;font-size:9px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.product-info small{color:#98a19b;font-size:8px}.product-info>i{height:4px;margin-top:2px}.product-info>i>b{background:#91a895}.product-orders{color:#36483f;font-size:10px;font-weight:700;text-align:right}.product-orders small{display:block;margin-top:3px;color:#98a19b;font-size:8px;font-weight:400}.activity-dot{display:flex;align-items:center;gap:5px;color:#9b8356;font-size:8px;font-weight:800;letter-spacing:.08em}.activity-dot i{width:6px;height:6px;border-radius:50%;background:#c8a765}.activity-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:9px;margin-top:15px}.activity-icon{width:28px;height:28px;display:grid;place-items:center;border-radius:8px}.activity-icon.order{background:#f7f0e4;color:#a07e43}.activity-icon.user{background:#eaf2ec;color:#5e8a6a}.activity-icon.complete{background:#eaf1f5;color:#6889a0}.activity-row>div{min-width:0;display:grid;gap:4px}.activity-row strong{overflow:hidden;color:#3b4b42;font-size:9px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.activity-row small{overflow:hidden;color:#8c9891;font-size:8px;text-overflow:ellipsis;white-space:nowrap}.activity-row time{color:#9aa49e;font-size:8px;white-space:nowrap}.activity-link{display:flex;align-items:center;justify-content:flex-end;gap:6px;margin-top:15px;padding-top:11px;border-top:1px solid #edf0ec;color:#607f69;font-size:9px;font-weight:700;text-decoration:none}.dashboard-footnote{display:flex;justify-content:space-between;gap:14px;margin-top:13px;color:#9aa39d;font-size:8px}.dashboard-footnote>span:last-child{display:flex;align-items:center;gap:3px;color:#a48654;font-weight:700;white-space:nowrap}@media(max-width:980px){.dashboard-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.dashboard-grid{grid-template-columns:1fr 1fr}.order-trend{grid-column:1/-1}}@media(max-width:640px){.dashboard-heading{align-items:flex-start;flex-direction:column}.dashboard-metrics{gap:9px}.metric-card{min-height:134px;padding:13px}.metric-card>strong{font-size:22px}.dashboard-grid{grid-template-columns:1fr}.order-trend{grid-column:auto}.dashboard-footnote{flex-direction:column}.activity-row{grid-template-columns:28px minmax(0,1fr)}.activity-row time{grid-column:2}.bar-chart{height:130px}}@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}
</style>
