<template>
  <div class="min-h-screen bg-gray-50">
    <n-config-provider :locale="naiveLocale" :date-locale="naiveDateLocale">
      <n-layout>
        <n-layout-header class="bg-white shadow-sm p-4">
          <div class="flex justify-between items-center max-w-6xl mx-auto">
            <div class="flex items-center space-x-4">
              <div class="i-tabler-brand-laravel text-2xl text-red-500" />
              <h1 class="text-xl font-bold text-gray-800">
                {{ $t('app.title') }}
              </h1>
            </div>
            <div class="flex items-center space-x-4">
              <n-button quaternary @click="toggleTheme">
                <div v-if="isDark" class="i-tabler-sun" />
                <div v-else class="i-tabler-moon" />
              </n-button>
              <n-select
                v-model:value="currentLocale"
                :options="localeOptions"
                class="w-32"
                @update:value="changeLocale"
              />
            </div>
          </div>
        </n-layout-header>

        <n-layout-content class="p-6">
          <div class="max-w-6xl mx-auto">
            <n-card class="mb-6">
              <template #header>
                <div class="flex items-center space-x-2">
                  <div class="i-tabler-rocket text-blue-500" />
                  <span>{{ $t('welcome.title') }}</span>
                </div>
              </template>

              <n-space vertical>
                <n-alert type="success" :title="$t('welcome.success')">
                  {{ $t('welcome.message') }}
                </n-alert>

                <n-divider />

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                  <n-card v-for="feature in features" :key="feature.name" :title="feature.name" size="small" hoverable>
                    <template #header-extra>
                      <div :class="feature.icon" class="text-lg" />
                    </template>
                    <p class="text-sm text-gray-600">
                      {{ feature.description }}
                    </p>
                  </n-card>
                </div>
              </n-space>
            </n-card>

            <n-card>
              <template #header>
                <div class="flex items-center space-x-2">
                  <div class="i-tabler-code text-green-500" />
                  <span>{{ $t('demo.title') }}</span>
                </div>
              </template>

              <n-space vertical>
                <n-button-group>
                  <n-button type="primary" @click="showModal = true">
                    {{ $t('demo.openModal') }}
                  </n-button>
                  <n-button type="info" @click="showNotification">
                    {{ $t('demo.showNotification') }}
                  </n-button>
                  <n-button type="success" @click="increment">{{ $t('demo.counter') }}: {{ count }}</n-button>
                </n-button-group>
              </n-space>
            </n-card>
          </div>
        </n-layout-content>
      </n-layout>
    </n-config-provider>

    <!-- Modal -->
    <n-modal v-model:show="showModal">
      <n-card
        style="width: 600px"
        :title="$t('modal.title')"
        :bordered="false"
        size="huge"
        role="dialog"
        aria-modal="true"
      >
        <p>{{ $t('modal.content') }}</p>
        <template #footer>
          <n-button @click="showModal = false">
            {{ $t('modal.close') }}
          </n-button>
        </template>
      </n-card>
    </n-modal>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed } from 'vue';
  import { useI18n } from 'vue-i18n';
  import { useMessage, useNotification } from 'naive-ui';
  import { zhCN, dateZhCN, enUS, dateEnUS } from 'naive-ui';
  import { useCounterStore } from '@/stores/counter';

  const { locale, t } = useI18n();
  const message = useMessage();
  const notification = useNotification();
  const counterStore = useCounterStore();

  // Theme
  const isDark = ref(false);

  // Locale
  const currentLocale = ref('zh-CN');
  const localeOptions = [
    { label: '中文', value: 'zh-CN' },
    { label: 'English', value: 'en' }
  ];

  // Naive UI locale
  const naiveLocale = computed(() => {
    return currentLocale.value === 'zh-CN' ? zhCN : enUS;
  });

  const naiveDateLocale = computed(() => {
    return currentLocale.value === 'zh-CN' ? dateZhCN : dateEnUS;
  });

  // Features
  const features = computed(() => [
    {
      name: 'Vue 3',
      description: t('features.vue3'),
      icon: 'i-tabler-brand-vue'
    },
    {
      name: 'TypeScript',
      description: t('features.typescript'),
      icon: 'i-tabler-brand-typescript'
    },
    {
      name: 'Vite',
      description: t('features.vite'),
      icon: 'i-tabler-bolt'
    },
    {
      name: 'UnoCSS',
      description: t('features.unocss'),
      icon: 'i-tabler-palette'
    },
    {
      name: 'Pinia',
      description: t('features.pinia'),
      icon: 'i-tabler-database'
    },
    {
      name: 'Naive UI',
      description: t('features.naiveui'),
      icon: 'i-tabler-components'
    }
  ]);

  // Modal
  const showModal = ref(false);

  // Counter from store
  const count = computed(() => counterStore.count);

  // Methods
  const toggleTheme = () => {
    isDark.value = !isDark.value;
  };

  const changeLocale = (value: string) => {
    locale.value = value;
    currentLocale.value = value;
  };

  const showNotification = () => {
    notification.success({
      title: t('notification.title'),
      content: t('notification.content'),
      duration: 3000
    });
  };

  const increment = () => {
    counterStore.increment();
    message.success(`${t('demo.counter')}: ${count.value}`);
  };
</script>
