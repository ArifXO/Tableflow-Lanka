import { defineConfig } from 'tailwindcss'

export default defineConfig({
  content: [
    './resources/**/*.vue',
    './resources/**/*.js',
    './resources/**/*.ts',
    './vendor/**/*.php'
  ],
  theme: {
    extend: {
      colors: {
        primary: '#3b1010',
        accent: '#3b1010',
        cream: '#f5f5dc'
      }
    }
  },
  plugins: []
})
