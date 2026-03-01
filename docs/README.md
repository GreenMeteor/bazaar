# HumHub Bazaar Module

A comprehensive module for HumHub that allows browsing and purchasing additional modules from Green Meteor via API integration.

## Features

- Browse available HumHub modules from Green Meteor
- Secure purchase integration
- Module details with screenshots and features
- Category-based filtering and search
- Responsive Bootstrap 5 design
- Admin configuration panel
- Caching for optimal performance
- Multi-language support

## Requirements

- HumHub 1.18.0 or higher
- PHP 8.2 or higher
- Active internet connection for API access
- Green Meteor API key (register at https://greenmeteor.com/developers)

## Configuration

### API Settings

1. **API Base URL**: Default is `https://api.greenmeteor.com/v1`
2. **API Key**: Your authentication key from Green Meteor
3. **Cache Timeout**: How long to cache API responses (default: 3600 seconds)
4. **Enable Purchasing**: Allow direct module purchases (default: enabled)

### Environment Variables (.env support)

You can also configure the module using environment variables:

```env
BAZAAR_API_BASE_URL=https://greenmeteor.net/api/modules
BAZAAR_API_KEY=your_api_key_here
BAZAAR_CACHE_TIMEOUT=3600
BAZAAR_ENABLE_PURCHASING=true
```

## Usage

### For Administrators

1. **Browse Modules**
   - Navigate to Administration → Module Bazaar
   - Browse available modules by category
   - Use search to find specific modules

2. **View Module Details**
   - Click "Details" on any module
   - View screenshots, features, and requirements
   - Check compatibility information

3. **Purchase Modules**
   - Click "Purchase" for paid modules
   - Review purchase details
   - Complete secure payment process
   - Download and install purchased modules

4. **Manage Cache**
   - Click "Clear Cache" to refresh module listings
   - Cache automatically expires based on timeout setting

### API Integration

The module communicates with Green Meteor's API using the following endpoints:

- `GET /modules` - Fetch available modules
- `GET /modules/{id}` - Get module details

- `POST /modules/{id}/purchase` - Purchase a module
