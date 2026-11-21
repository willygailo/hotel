/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './pages/**/*.php',
    './auth/**/*.php',
    './user/**/*.php',
    './staff/**/*.php',
    './admin/**/*.php',
    './components/**/*.php',
    './assets/js/**/*.js'
  ],
  corePlugins: {
    // Disabled so Tailwind utilities coexist seamlessly with base.css/public.css/admin.css
    preflight: false
  },
  theme: {
    extend: {
      colors: {
        navy: {
          DEFAULT: '#0A0F2C',
          dark: '#06091E',
          card: '#111842',
          light: '#1A2254',
          hover: '#232D6B'
        },
        gold: {
          DEFAULT: '#C9A84C',
          light: '#E2C775',
          dark: '#9A7A2E'
        }
      },
      fontFamily: {
        sans: ['Outfit', 'sans-serif'],
        serif: ['Playfair Display', 'serif']
      }
    }
  },
  plugins: []
};
