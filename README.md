# AI Website Builder - Backend API & WordPress Plugin

This repository contains the backend components for the AI Website Builder application.

## 🚀 Components

### 📡 API Services
- **`api/ai-service.php`** - AI content generation service (Gemini, Unsplash)
- **`api/api-site-config.php`** - Site configuration management API

### ⚙️ Configuration
- **`config/constants.php`** - API keys and server configurations

### 🔌 WordPress Plugin
- **`wordpress/wp-content/plugins/ai-web-site/`** - Complete WordPress plugin
  - Subdomain management via cPanel API
  - Site configuration storage
  - Admin interface for subdomain creation
  - REST API endpoints

## 📋 Installation

### For WordPress Integration

1. **Upload Plugin**:
   ```bash
   # Copy the plugin to your WordPress installation
   cp -r wordpress/wp-content/plugins/ai-web-site/ /path/to/wordpress/wp-content/plugins/
   ```

2. **Activate Plugin**:
   - Go to WordPress Admin → Plugins
   - Find "AI Web Site" plugin
   - Click "Activate"

3. **Configure API Keys**:
   - Go to AI Web Site → Settings
   - Add your cPanel API token
   - Configure API endpoints

### For Direct API Usage

1. **Upload API Files**:
   ```bash
   # Copy API files to your web server
   cp -r api/ /path/to/your/website/api/
   cp -r config/ /path/to/your/website/config/
   ```

2. **Configure Constants**:
   - Edit `config/constants.php`
   - Add your API keys and configurations

## 🔧 Configuration

### Required API Keys

Update `config/constants.php` with your credentials:

```php
// Google Gemini API
define('GEMINI_API_KEY', 'your_gemini_api_key');

// Unsplash API
define('UNSPLASH_ACCESS_KEY', 'your_unsplash_key');

// cPanel API
define('CPANEL_API_TOKEN', 'your_cpanel_token');
define('CPANEL_USERNAME', 'your_cpanel_username');
define('CPANEL_DOMAIN', 'your-domain.com');
```

## 📡 API Endpoints

### Site Configuration
- **GET** `/api/api-site-config.php?subdomain=example` - Get site config
- **POST** `/api/api-site-config.php` - Save site config

### AI Services
- **POST** `/api/ai-service.php` - Generate AI content
  - Parameters: `prompt`, `type`, `subdomain`

## 🔌 WordPress Plugin Features

### Admin Interface
- **Subdomain Management**: Create/delete subdomains via cPanel API
- **Site Configuration**: Manage site configs per subdomain
- **API Key Management**: Secure storage of API credentials

### REST API
- **GET** `/wp-json/ai-web-site/v1/site-config/{subdomain}` - Get config
- **POST** `/wp-json/ai-web-site/v1/site-config/{subdomain}` - Save config

## 🔄 Auto-Update

This repository is automatically updated when:
1. Backend changes are made in the main application
2. New features are added to the WordPress plugin
3. API improvements are implemented

## 📝 Development

### Plugin Development
```bash
# The plugin is located in:
wordpress/wp-content/plugins/ai-web-site/

# Main files:
- ai-web-site.php (Plugin header)
- includes/class-ai-web-site.php (Main plugin class)
- includes/class-cpanel-api.php (cPanel integration)
- includes/class-database.php (Database operations)
- admin/ (Admin interface)
```

### API Development
```bash
# API files are in:
api/
├── ai-service.php
└── api-site-config.php

# Configuration:
config/
└── constants.php
```

## 🛡️ Security

- All API keys are stored securely
- Input validation on all endpoints
- cPanel API token authentication
- WordPress nonce verification for admin actions

## 📞 Support

For issues or questions:
1. Check the main repository documentation
2. Review the WordPress plugin admin interface
3. Check API endpoint responses for errors

---

**Last Updated**: $(date)
