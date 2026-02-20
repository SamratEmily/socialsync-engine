/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [ './src/**/*.{js,jsx}' ],
    // Prefix all utilities so they never collide with WP Admin styles.
    // Remove the `prefix` line if you scope via the `.sse-wrap` parent instead.
    theme: {
        extend: {},
    },
    plugins: [],
    corePlugins: {
        // Disable Tailwind's global preflight reset – WP Admin already has its own.
        preflight: false,
    },
};
