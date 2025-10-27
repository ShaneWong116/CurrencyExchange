<template>
  <q-page class="transaction-type-page">
    <!-- Header -->
    <header class="page-header">
      <div class="header-content">
        <q-btn 
          flat 
          round 
          icon="arrow_back" 
          @click="$router.back()" 
          class="back-btn"
        />
        <div class="header-title">交易录入</div>
      </div>
    </header>

    <!-- Content -->
    <div class="content-section">
      <div class="section-intro">
        <div class="intro-title">选择交易类型</div>
        <div class="intro-subtitle">请选择要进行的交易操作类型</div>
      </div>

      <div class="cards-container">
        <div 
          v-for="item in typeCards" 
          :key="item.id"
          class="type-card-wrapper"
        >
          <div 
            class="type-card" 
            :class="item.colorClass"
            @click="selectTransactionType(item.id)"
          >
            <div class="card-icon-wrapper" :class="item.iconBgClass">
              <q-icon :name="item.icon" size="36px" class="card-icon" />
            </div>
            <div class="card-title">{{ item.label }}</div>
            <div class="card-description">{{ item.description }}</div>
            <div class="card-arrow">
              <q-icon name="arrow_forward" size="20px" />
            </div>
          </div>
        </div>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { useRouter } from 'vue-router'

const router = useRouter()

const typeCards = [
  { 
    id: 'deposit', 
    label: '入账', 
    icon: 'add_circle', 
    color: 'positive', 
    description: '人民币增加，港币减少',
    colorClass: 'income-card',
    iconBgClass: 'income-icon-bg'
  },
  { 
    id: 'withdrawal', 
    label: '出账', 
    icon: 'remove_circle', 
    color: 'negative', 
    description: '人民币减少，港币增加',
    colorClass: 'outcome-card',
    iconBgClass: 'outcome-icon-bg'
  },
  { 
    id: 'instant-buyout', 
    label: '即时买断', 
    icon: 'swap_horiz', 
    color: 'info', 
    description: '一次同时记录入账与出账',
    colorClass: 'instant-card',
    iconBgClass: 'instant-icon-bg'
  }
]

const selectTransactionType = (type) => {
  router.push(`/transaction/entry/${type}`)
}
</script>

<style scoped>
/* Page Layout */
.transaction-type-page {
  background: #f5f5f5;
  min-height: 100vh;
}

/* Header */
.page-header {
  background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
  padding: 12px 20px 18px;
  box-shadow: 0 4px 20px rgba(25, 118, 210, 0.3);
  position: relative;
  overflow: hidden;
}

.page-header::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -20%;
  width: 200px;
  height: 200px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
  z-index: 1;
}

.back-btn {
  color: white !important;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
}

.header-title {
  font-size: 20px;
  font-weight: 700;
  color: white;
  text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: 0.5px;
}

/* Content Section */
.content-section {
  padding: 16px 16px 20px;
}

.section-intro {
  margin-bottom: 16px;
}

.intro-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 6px;
  letter-spacing: 0.3px;
}

.intro-subtitle {
  font-size: 13px;
  color: #757575;
  font-weight: 400;
}

/* Cards Container */
.cards-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 12px;
}

/* Type Cards */
.type-card {
  background: white;
  border-radius: 16px;
  padding: 20px 16px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  position: relative;
  overflow: hidden;
  min-height: 160px;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.type-card::before {
  content: '';
  position: absolute;
  top: 0;
  right: 0;
  width: 120px;
  height: 120px;
  border-radius: 50%;
  opacity: 0.08;
  transition: all 0.3s ease;
}

.income-card::before {
  background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
}

.outcome-card::before {
  background: linear-gradient(135deg, #ff4d4f 0%, #ff7875 100%);
}

.instant-card::before {
  background: linear-gradient(135deg, #722ed1 0%, #9254de 100%);
}

.type-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);
}

.type-card:hover::before {
  opacity: 0.12;
  transform: scale(1.2);
}

.type-card:active {
  transform: translateY(-4px);
}

/* Card Icon */
.card-icon-wrapper {
  width: 64px;
  height: 64px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 12px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
  transition: all 0.3s ease;
  position: relative;
  z-index: 1;
}

.type-card:hover .card-icon-wrapper {
  transform: scale(1.1) rotate(5deg);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
}

.card-icon {
  color: white;
}

.income-icon-bg {
  background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
}

.outcome-icon-bg {
  background: linear-gradient(135deg, #ff4d4f 0%, #ff7875 100%);
}

.instant-icon-bg {
  background: linear-gradient(135deg, #722ed1 0%, #9254de 100%);
}

/* Card Text */
.card-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  margin-bottom: 6px;
  letter-spacing: 0.3px;
  position: relative;
  z-index: 1;
}

.card-description {
  font-size: 13px;
  color: #757575;
  line-height: 1.4;
  margin-bottom: 8px;
  position: relative;
  z-index: 1;
}

/* Card Arrow */
.card-arrow {
  position: absolute;
  bottom: 16px;
  right: 16px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transform: translateX(-10px);
  transition: all 0.3s ease;
}

.type-card:hover .card-arrow {
  opacity: 1;
  transform: translateX(0);
}

.card-arrow .q-icon {
  color: #666;
}

/* Responsive */
@media (max-width: 600px) {
  .cards-container {
    grid-template-columns: 1fr;
  }
  
  .intro-title {
    font-size: 17px;
  }
  
  .type-card {
    min-height: 145px;
    padding: 16px 12px;
  }
  
  .card-icon-wrapper {
    width: 56px;
    height: 56px;
  }
  
  .card-icon-wrapper .q-icon {
    font-size: 32px !important;
  }
  
  .card-title {
    font-size: 16px;
  }
  
  .card-description {
    font-size: 12px;
  }
}
</style>
