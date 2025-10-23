<template>
  <div class="bottom-nav bg-white">
    <q-btn flat stack no-caps class="nav-btn" :color="isActive('/home') ? 'primary' : 'grey-6'" icon="home" label="首页" :to="{ path: '/home' }" />
    <q-btn flat stack no-caps class="nav-btn" :color="isActive('/records') ? 'primary' : 'grey-6'" icon="list_alt" label="记录" :to="{ path: '/records' }" />
    <q-btn flat stack no-caps class="nav-btn" :color="isActive('/profile') ? 'primary' : 'grey-6'" icon="person" label="个人" :to="{ path: '/profile' }" />
  </div>
</template>

<script>
export default {
  name: 'BottomNavigation',
  methods: {
    isActive(path) {
      return this.$route.path === path
    }
  }
}
</script>

<style scoped>
.bottom-nav {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 1000;
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  border-top: 1px solid #eee;
  padding: 6px 8px calc(env(safe-area-inset-bottom) + 6px);
}
.nav-btn { width: 100%; }
</style>
