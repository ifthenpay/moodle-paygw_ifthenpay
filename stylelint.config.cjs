module.exports = {
  extends: ['stylelint-config-standard'],
  plugins: ['@stylistic/stylelint-plugin'],
  rules: {
    '@stylistic/indentation': 4
  },
  ignoreFiles: [
    'node_modules/**',
    'vendor/**'
  ]
};
