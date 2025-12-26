# Contributing to xMoney for WooCommerce

Thank you for your interest in contributing to xMoney for WooCommerce! We welcome contributions from the community and are grateful for your help in making this plugin better.

## Getting Started

1. **Fork the repository** on GitHub

2. **Clone your fork** locally:

   ```bash
   git clone https://github.com/YOUR_USERNAME/xmoney-woocommerce.git
   cd xmoney-woocommerce
   ```

3. **Set up a local WordPress environment** with WooCommerce installed

4. **Symlink or copy the plugin** to your WordPress plugins directory:

   ```bash
   ln -s /path/to/xmoney-woocommerce /path/to/wordpress/wp-content/plugins/
   ```

5. **Activate the plugin** in WordPress Admin → Plugins

6. **Create a branch** for your feature or bug fix:

   ```bash
   git checkout -b my-new-feature
   ```

## Development Workflow

### Local Development

We recommend using one of these tools for local WordPress development:

- [Local](https://localwp.com/) - Simple WordPress development environment
- [Docker](https://www.docker.com/) - Container-based development
- [MAMP](https://www.mamp.info/) / [XAMPP](https://www.apachefriends.org/) - Traditional PHP stack

### Code Style

- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use tabs for indentation (not spaces)
- Use proper PHPDoc comments for functions and classes
- Prefix all functions, classes, and hooks with `xmoney_wc_`

### Testing

- Test with both Classic Checkout and Blocks Checkout
- Test with the latest versions of WordPress and WooCommerce
- Test in both test mode and live mode (with test credentials)
- Verify compatibility with popular themes

## Making Changes

### Bug Fixes

1. Identify and document the bug
2. Write a fix that follows our coding standards
3. Test thoroughly across different environments
4. Submit a pull request with a clear description

### New Features

1. Open an issue first to discuss the feature
2. Get approval before starting work
3. Follow the existing code architecture
4. Include documentation updates
5. Test across Classic and Blocks checkout

### File Guidelines

- **PHP files** - Follow WordPress coding standards
- **JavaScript files** - Use vanilla JS for compatibility
- **CSS files** - Use BEM-style naming conventions

## Pull Request Process

1. **Update your branch** with the latest changes from `master`:

   ```bash
   git checkout master
   git pull upstream master
   git checkout my-new-feature
   git rebase master
   ```

2. **Ensure your code:**
   - Follows WordPress coding standards
   - Works with both checkout types
   - Doesn't introduce PHP warnings or errors
   - Is properly documented

3. **Push your branch** to your fork:

   ```bash
   git push origin my-new-feature
   ```

4. **Submit a Pull Request** to the `master` branch
   - Provide a clear, descriptive title
   - Explain what changes you made and why
   - Reference any related issues
   - Include screenshots for UI changes

5. **Respond to feedback** - We may request changes or ask questions

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers and help them learn
- Focus on constructive feedback
- Celebrate others' contributions

## What to Contribute

We welcome contributions in many forms:

- 🐛 **Bug fixes** - Report and fix issues
- ✨ **New features** - Enhance the plugin functionality
- 📖 **Documentation** - Improve README and inline docs
- 🌍 **Translations** - Help localize the plugin
- 🧪 **Testing** - Test with different WordPress/WooCommerce versions

## Reporting Issues

If you find a bug or have a suggestion:

1. Check if the issue already exists
2. Create a new issue with:
   - Clear title and description
   - Steps to reproduce (for bugs)
   - Expected vs actual behavior
   - WordPress/WooCommerce/PHP versions
   - Screenshots if applicable
   - Browser and checkout type (Classic/Blocks)

## License

By contributing, you agree that your contributions will be licensed under the MIT license.

Thank you for contributing to xMoney for WooCommerce! 🎉
