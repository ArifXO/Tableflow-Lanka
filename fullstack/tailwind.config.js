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
        // Provide color in rgb() with <alpha-value> placeholder to enable slash opacity syntax (e.g. bg-primary/10)
        primary: 'rgb(59 16 16 / <alpha-value>)',
        accent: 'rgb(59 16 16 / <alpha-value>)',
        cream: '#f5f5dc'
      }
    }
  },
  plugins: []
})
