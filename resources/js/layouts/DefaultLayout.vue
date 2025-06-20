<template>
  <NConfigProvider class="h-full" :theme="darkTheme">
    <NLoadingBarProvider>
      <NDialogProvider>
        <NNotificationProvider>
          <NMessageProvider>
            <slot />
            <NaiveProviderContent />
          </NMessageProvider>
        </NNotificationProvider>
      </NDialogProvider>
    </NLoadingBarProvider>
  </NConfigProvider>
</template>

<script setup lang="ts">
  import { defineComponent, h } from 'vue';
  import { darkTheme } from 'naive-ui';
  import {
    NConfigProvider,
    NLoadingBarProvider,
    NDialogProvider,
    NNotificationProvider,
    NMessageProvider,
    useDialog,
    useLoadingBar,
    useMessage,
    useNotification
  } from 'naive-ui';

  function registerNaiveTools() {
    window.$loadingBar = useLoadingBar();
    window.$dialog = useDialog();
    window.$message = useMessage();
    window.$notification = useNotification();
  }

  const NaiveProviderContent = defineComponent({
    name: 'NaiveProviderContent',

    setup() {
      registerNaiveTools();
    },

    render() {
      return h('div');
    }
  });
</script>
