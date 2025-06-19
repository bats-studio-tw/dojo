import js from '@eslint/js';
import vue from 'eslint-plugin-vue';
import typescript from '@typescript-eslint/eslint-plugin';
import typescriptParser from '@typescript-eslint/parser';
import vueParser from 'vue-eslint-parser';
import unocss from '@unocss/eslint-plugin';
import prettier from 'eslint-plugin-prettier';

export default [
  {
    ignores: [
      'node_modules/**',
      'vendor/**',
      'storage/**',
      'bootstrap/cache/**',
      'public/build/**',
      'public/hot',
      'dist/**',
      '*.config.js',
      'vite.config.js',
      'postcss.config.js',
      'uno.config.ts'
    ]
  },
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  // UnoCSS configuration
  unocss.configs.flat,
  {
    files: ['**/*.{js,ts,vue}'],
    languageOptions: {
      parser: vueParser,
      parserOptions: {
        parser: typescriptParser,
        ecmaVersion: 'latest',
        sourceType: 'module',
        extraFileExtensions: ['.vue']
      },
      globals: {
        // Browser globals
        window: 'readonly',
        document: 'readonly',
        console: 'readonly',
        // Node globals
        process: 'readonly',
        // Vite globals
        import: 'readonly'
      }
    },
    plugins: {
      '@typescript-eslint': typescript,
      vue,
      prettier
    },
    rules: {
      // Prettier rules
      'prettier/prettier': 'error',

      // Vue rules
      'vue/multi-word-component-names': 'off',
      'vue/no-unused-vars': 'error',
      'vue/no-mutating-props': 'warn',
      'vue/require-default-prop': 'off',
      'vue/require-prop-types': 'warn',

      // TypeScript rules
      '@typescript-eslint/no-unused-vars': 'error',
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/explicit-function-return-type': 'off',
      '@typescript-eslint/explicit-module-boundary-types': 'off',
      '@typescript-eslint/no-shadow': 'error',
      '@typescript-eslint/no-unused-expressions': 'error',
      '@typescript-eslint/no-use-before-define': ['error'],
      '@typescript-eslint/no-useless-constructor': ['error'],

      // General rules
      'no-console': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
      'no-debugger': process.env.NODE_ENV === 'production' ? 'warn' : 'off',
      'no-unused-vars': 'off', // Use TypeScript version instead
      'no-shadow': 'off', // Use TypeScript version instead
      'no-unused-expressions': 'off', // Use TypeScript version instead
      'no-use-before-define': 'off', // Use TypeScript version instead
      'no-useless-constructor': 'off', // Use TypeScript version instead
      'no-undef': 'off', // TypeScript handles this
      'no-fallthrough': ['error', { allowEmptyCase: true }],
      'prefer-const': 'error',
      'no-var': 'error',
      'object-shorthand': 'error',
      'prefer-template': 'error',
      'prefer-destructuring': 'off',
      'import/order': 'off',
      'import/no-extraneous-dependencies': 'off'
    }
  },
  {
    files: ['**/*.vue'],
    rules: {
      // Vue specific overrides
      'no-undef': 'off' // Vue SFC compiler handles this
    }
  },
  {
    files: ['**/*.config.{js,ts}', 'vite.config.{js,ts}'],
    rules: {
      'no-console': 'off'
    }
  }
];
