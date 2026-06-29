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
            fontFamily: {
                sans: ['"IBM Plex Sans Arabic"', '"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
                mono: ['"IBM Plex Mono"', '"IBM Plex Sans Arabic"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // Wareed design system (docs/whatsapp-bot-saas-plan.html)
                emerald: {
                    DEFAULT: '#0E7C5C',
                    deep: '#0A5C44',
                },
                signal: '#16C892',
                gold: {
                    DEFAULT: '#B8862E',
                    soft: '#C8973F',
                },
                night: {
                    DEFAULT: '#072019',
                    2: '#0A2B22',
                },
                paper: '#EEF2F0',
                ink: {
                    DEFAULT: '#0B1F1A',
                    2: '#33463F',
                    soft: '#5E726A',
                },
            },
            borderRadius: {
                xl: '14px',
            },
            boxShadow: {
                luxe: '0 14px 34px -22px rgba(11,31,26,.4)',
                'luxe-lg': '0 24px 60px -30px rgba(11,31,26,.45)',
            },
        },
    },

    plugins: [forms],
};
