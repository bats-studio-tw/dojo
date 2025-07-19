import { defineConfig, presetUno, presetAttributify, presetIcons } from 'unocss';
import transformerDirectives from '@unocss/transformer-directives';
import transformerVariantGroup from '@unocss/transformer-variant-group';

export default defineConfig({
  presets: [
    presetUno(),
    presetAttributify(),
    presetIcons({
      collections: {
        tabler: () => import('@iconify-json/tabler/icons.json').then((i) => i.default),
        carbon: () => import('@iconify-json/carbon/icons.json').then((i) => i.default)
      }
    })
  ],
  transformers: [transformerDirectives(), transformerVariantGroup()],
  shortcuts: {
    btn: 'px-4 py-2 rounded inline-block bg-teal-600 text-white cursor-pointer hover:bg-teal-700 disabled:cursor-default disabled:bg-gray-600 disabled:opacity-50',
    'btn-primary': 'bg-blue-500 hover:bg-blue-600',
    'btn-secondary': 'bg-gray-500 hover:bg-gray-600'
  },
  theme: {
    colors: {
      primary: '#1976d2',
      secondary: '#424242'
    }
  }
});
