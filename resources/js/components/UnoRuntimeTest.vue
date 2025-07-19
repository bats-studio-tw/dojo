<template>
  <div class="rounded-lg bg-gray-100 p-6">
    <h3 class="mb-4 text-lg font-bold">UnoCSS Runtime 测试</h3>

    <!-- 静态类名测试 -->
    <div class="mb-4">
      <h4 class="mb-2 font-semibold">静态类名测试:</h4>
      <div class="rounded bg-blue-500 p-3 text-white">静态蓝色背景</div>
    </div>

    <!-- 动态类名测试 -->
    <div class="mb-4">
      <h4 class="mb-2 font-semibold">动态类名测试:</h4>
      <button @click="changeColor" :class="dynamicClasses" class="rounded px-4 py-2 transition-all duration-300">
        点击切换颜色 (当前: {{ currentColor }})
      </button>
    </div>

    <!-- 运行时生成的类名测试 -->
    <div class="mb-4">
      <h4 class="mb-2 font-semibold">运行时生成类名测试:</h4>
      <div :class="runtimeGeneratedClass" class="rounded p-3 text-white font-bold">
        这个类名是在运行时生成的: {{ runtimeGeneratedClass }}
      </div>
    </div>

    <!-- 自定义快捷方式测试 -->
    <div>
      <h4 class="mb-2 font-semibold">自定义快捷方式测试:</h4>
      <button class="mr-2 btn">默认按钮</button>
      <button class="mr-2 btn btn-primary">主要按钮</button>
      <button class="btn btn-secondary">次要按钮</button>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted } from 'vue';

  // 响应式颜色状态
  const currentColor = ref('red');
  const colors = ['red', 'green', 'blue', 'purple', 'yellow', 'pink'];

  // 动态类名
  const dynamicClasses = computed(() => {
    return `bg-${currentColor.value}-500 hover:bg-${currentColor.value}-600 text-white`;
  });

  // 运行时生成的类名（模拟API返回的动态数据）
  const runtimeGeneratedClass = computed(() => {
    const randomNumber = Math.floor(Math.random() * 9) + 1;
    return `bg-cyan-${randomNumber}00`;
  });

  // 切换颜色
  const changeColor = () => {
    const currentIndex = colors.indexOf(currentColor.value);
    const nextIndex = (currentIndex + 1) % colors.length;
    currentColor.value = colors[nextIndex];
  };

  // 检查Runtime状态
  const checkRuntimeStatus = () => {
    console.log('🔍 检查 UnoCSS Runtime 状态...');

    // 检查是否有runtime相关的style标签
    const runtimeStyles = document.querySelectorAll('style[data-unocss-runtime], style[uno-css-runtime]');
    const hasStyleElement = runtimeStyles.length > 0;

    // 检查window对象
    const hasWindowUno = !!(window as any).__unocss_runtime || !!(window as any).__unocss;

    console.log('Runtime样式标签数量:', runtimeStyles.length);
    console.log('Window UnoCSS对象:', hasWindowUno);
    console.log('当前动态类名:', dynamicClasses.value);
    console.log('运行时生成类名:', runtimeGeneratedClass.value);

    return {
      hasStyleElement,
      hasWindowUno,
      styleElementsCount: runtimeStyles.length
    };
  };

  // 在组件挂载时检查状态
  onMounted(() => {
    setTimeout(checkRuntimeStatus, 1000);
  });
</script>
