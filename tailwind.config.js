import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            screens: {
                xs: '30rem',
            },
            maxWidth: {
                'fm': 'var(--fm-container-max)',
                'fm-wide': 'var(--fm-container-wide-max)',
            },
            spacing: {
                'nav': 'var(--fm-nav-height)',
                'touch': 'var(--fm-touch-min)',
            },
            fontFamily: {
                sans: ['Outfit', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                fm: {
                    bg: '#0B0F19',
                    surface: '#12182A',
                    'surface-elevated': '#1A2238',
                    border: '#1E2A3D',
                    'border-strong': '#2A3850',
                    primary: '#00FF88',
                    'primary-hover': '#00E67A',
                    'primary-muted': 'rgba(0, 255, 136, 0.12)',
                    accent: '#FF6D00',
                    'accent-hover': '#E65100',
                    text: '#FFFFFF',
                    'text-body': '#C8D0DC',
                    muted: '#8B95A5',
                    disabled: '#5A6474',
                    protein: '#00FF88',
                    carbs: '#4A90D9',
                    fat: '#FF6D00',
                    kcal: '#00BFA5',
                    warning: '#FFB300',
                    danger: '#FF5252',
                },
            },
            fontSize: {
                display: ['3.75rem', { lineHeight: '1.1', fontWeight: '700' }],
                h1: ['2.25rem', { lineHeight: '1.2', fontWeight: '700' }],
                h2: ['1.5rem', { lineHeight: '1.3', fontWeight: '600' }],
                h3: ['1.125rem', { lineHeight: '1.4', fontWeight: '600' }],
                body: ['1rem', { lineHeight: '1.5', fontWeight: '400' }],
                'body-sm': ['0.875rem', { lineHeight: '1.5' }],
                caption: ['0.75rem', { lineHeight: '1.4', fontWeight: '400' }],
                stat: ['2rem', { lineHeight: '1.2', fontWeight: '700' }],
            },
            boxShadow: {
                'fm-card': 'var(--fm-shadow-card)',
            },
        },
    },

    plugins: [forms],
};
