export default {
  plugins: {
    'autoprefixer': {},
    'postcss-preset-env': {
      stage: 1,
      features: {
        'nesting-rules': true,
        'custom-properties': true
      }
    },
    'cssnano': {
      preset: ['default', {
        discardComments: { removeAll: true },
        normalizeWhitespace: true,
        minifySelectors: true
      }]
    }
  }
}