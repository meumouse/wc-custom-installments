/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{vue,js,ts}', '../templates/**/*.php'],
  important: true,
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#0088ff',
          0: '#ffffff',
          50: '#e4eef6',
          100: '#d3e6f6',
          200: '#b2d7f8',
          300: '#90c8f9',
          400: '#6fb9fa',
          500: '#4daafc',
          600: '#2c9bfd',
          700: '#0a8cff',
          800: '#0267bf',
          900: '#053763',
          950: '#072036',
        },
        shell: {
          50: '#f4f7fb',
          100: '#e8eef8',
          200: '#cddcf1',
          300: '#a7c2e5',
          400: '#7aa1d1',
          500: '#4a78b4',
          600: '#32558a',
          700: '#27436b',
          800: '#1f3656',
          900: '#17253d',
        },
        ink: '#102033',
        success: '#22c55e',
        danger: '#ef4444',
        warning: '#ffba08',
        info: '#0ea5e9',
        dark: '#212529',
        panel: '#ffffff',
        muted: '#6b7280',
      },
      boxShadow: {
        soft: '0 18px 50px rgba(16, 32, 51, 0.12)',
      },
      borderRadius: {
        '2xl': '1.25rem',
      },
    },
  },
  plugins: [],
};
