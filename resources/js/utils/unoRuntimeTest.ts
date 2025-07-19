/**
 * UnoCSS Runtime 验证工具
 * 可以在浏览器控制台中调用这些函数来测试Runtime是否正常工作
 */

// 测试动态创建元素并应用UnoCSS类
export function testRuntimeCSS() {
  console.log('🧪 开始测试 UnoCSS Runtime...');

  // 创建测试容器
  const testContainer = document.createElement('div');
  testContainer.id = 'uno-runtime-test';
  testContainer.style.position = 'fixed';
  testContainer.style.top = '10px';
  testContainer.style.right = '10px';
  testContainer.style.zIndex = '9999';
  testContainer.style.padding = '10px';
  testContainer.style.background = 'white';
  testContainer.style.border = '1px solid #ccc';
  testContainer.style.borderRadius = '8px';
  testContainer.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';

  // 测试1: 基础UnoCSS类
  const basicTest = document.createElement('div');
  basicTest.className = 'bg-blue-500 text-white p-3 rounded mb-2';
  basicTest.textContent = '✅ 基础UnoCSS类测试';

  // 测试2: 动态生成的类
  const dynamicTest = document.createElement('div');
  const randomColor = ['red', 'green', 'purple', 'yellow', 'pink'][Math.floor(Math.random() * 5)];
  const randomNumber = Math.floor(Math.random() * 9) + 1;
  dynamicTest.className = `bg-${randomColor}-${randomNumber}00 text-white p-3 rounded mb-2`;
  dynamicTest.textContent = `✅ 动态类: bg-${randomColor}-${randomNumber}00`;

  // 测试3: 自定义快捷方式
  const shortcutTest = document.createElement('button');
  shortcutTest.className = 'btn btn-primary';
  shortcutTest.textContent = '✅ 快捷方式测试';

  // 测试4: 复杂组合类
  const complexTest = document.createElement('div');
  complexTest.className =
    'flex items-center justify-between bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-3 rounded shadow-lg transform hover:scale-105 transition-all duration-300';
  complexTest.textContent = '✅ 复杂组合类测试';

  // 添加关闭按钮
  const closeBtn = document.createElement('button');
  closeBtn.className = 'absolute top-1 right-1 w-6 h-6 bg-red-500 text-white rounded-full text-xs hover:bg-red-600';
  closeBtn.textContent = '×';
  closeBtn.onclick = () => {
    document.body.removeChild(testContainer);
    console.log('🧪 UnoCSS Runtime 测试窗口已关闭');
  };

  // 组装测试容器
  testContainer.appendChild(closeBtn);
  testContainer.appendChild(basicTest);
  testContainer.appendChild(dynamicTest);
  testContainer.appendChild(shortcutTest);
  testContainer.appendChild(complexTest);

  // 添加到页面
  document.body.appendChild(testContainer);

  console.log('🎉 UnoCSS Runtime 测试完成！检查右上角的测试窗口');
  console.log('💡 如果样式正确显示，说明Runtime工作正常');
  console.log('❌ 如果样式不正确，可能需要检查Runtime配置');

  return {
    container: testContainer,
    success: true,
    message: 'UnoCSS Runtime 测试窗口已创建'
  };
}

// 测试特定的动态类名
export function testDynamicClass(className: string) {
  console.log(`🧪 测试动态类名: ${className}`);

  const testElement = document.createElement('div');
  testElement.className = className;
  testElement.textContent = `测试类名: ${className}`;
  testElement.style.position = 'fixed';
  testElement.style.top = '50%';
  testElement.style.left = '50%';
  testElement.style.transform = 'translate(-50%, -50%)';
  testElement.style.padding = '20px';
  testElement.style.zIndex = '10000';
  testElement.style.border = '2px solid #000';

  document.body.appendChild(testElement);

  setTimeout(() => {
    document.body.removeChild(testElement);
    console.log(`✅ 动态类名测试完成: ${className}`);
  }, 3000);

  return testElement;
}

// 检查UnoCSS Runtime状态
export function checkRuntimeStatus() {
  console.log('🔍 检查 UnoCSS Runtime 状态...');

  // 检查window对象上的UnoCSS相关属性
  const hasUnoCSS = !!(window as any).__unocss_runtime;
  const hasStyleElement = document.querySelector('style[data-unocss-runtime]');

  const status = {
    runtime: hasUnoCSS ? '✅ 已加载' : '❌ 未找到',
    styleElement: hasStyleElement ? '✅ 样式元素存在' : '❌ 样式元素不存在',
    version: hasUnoCSS ? (window as any).__unocss_runtime?.version || '未知' : 'N/A'
  };

  console.table(status);

  return status;
}

// 全局暴露测试函数（仅在开发环境）
if (import.meta.env.DEV) {
  (window as any).testUnoCSS = testRuntimeCSS;
  (window as any).testDynamicClass = testDynamicClass;
  (window as any).checkUnoRuntimeStatus = checkRuntimeStatus;

  console.log('🛠️ UnoCSS Runtime 测试工具已加载');
  console.log('💡 在控制台中运行以下命令进行测试:');
  console.log('   - testUnoCSS() // 完整测试');
  console.log('   - testDynamicClass("bg-red-500 p-4") // 测试特定类名');
  console.log('   - checkUnoRuntimeStatus() // 检查状态');
}
