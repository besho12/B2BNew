<script setup lang="ts">
import { ArrowUpRight, CheckCircle2, ShoppingCart, Sparkles } from '@lucide/vue'
</script>

<template>
  <section class="auth-story">
    <div class="auth-brand"><div class="brand-mark light">B</div><div><strong>B2B</strong><small>PORTAL</small></div></div>
    <div class="auth-story-main">
      <div class="story-copy">
        <span class="eyebrow light">MUC-OFF TRADE PORTAL</span>
        <h1>Good gear.<br><em>Ready to ride.</em></h1>
        <p>Shop the Muc-Off range, build orders, and manage your trade account in one place.</p>
        <ul>
          <li><CheckCircle2 /> Browse products and promotions</li>
          <li><CheckCircle2 /> See account-specific availability and pricing</li>
          <li><CheckCircle2 /> Manage orders and account details</li>
        </ul>
      </div>
      <div class="storefront-preview" aria-label="Trade catalogue preview">
        <div class="storefront-heading"><span><i></i> TRADE CATALOGUE</span><span class="storefront-pricing">ACCOUNT PRICING</span></div>
        <div class="storefront-hero-product">
          <span class="storefront-product-tag"><Sparkles :size="11"/> FEATURED RANGE</span>
          <img :src="'/images/demo-products/product-01.svg'" alt="Illustration of a bike wash product">
          <div class="storefront-product-name"><span>CARE &amp; CLEAN</span><strong>Keep every ride fresh.</strong></div>
        </div>
        <div class="storefront-product-strip">
          <article><img :src="'/images/demo-products/product-08.svg'" alt="Illustration of a chain brush product"><span>CHAIN CARE</span></article>
          <article><img :src="'/images/demo-products/product-09.svg'" alt="Illustration of a microfiber cloth product"><span>RIDE ESSENTIALS</span></article>
        </div>
        <div class="storefront-cart"><span class="storefront-cart-icon"><ShoppingCart :size="16"/></span><span><strong>Quick order</strong><small>Find products by code</small></span><ArrowUpRight :size="16"/></div>
      </div>
    </div>
    <footer><span>PRODUCTS FOR THE RIDE</span><span>SECURE CUSTOMER ACCESS</span></footer>
  </section>
</template>
