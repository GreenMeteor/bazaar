## Installation

1. **Download and Extract**
   ```bash
   cd /path/to/humhub/protected/modules
   git clone https://github.com/greenmeteor/humhub-bazaar.git bazaar
   ```

2. **Set Permissions**
   ```bash
   chmod -R 755 bazaar/
   chown -R www-data:www-data bazaar/
   ```

3. **Enable Module**
   - Go to Administration → Modules
   - Find "Module Bazaar" and click "Enable"

4. **Configure API Access**
   - Go to Administration → Module Bazaar
   - Click "Configure" button
   - Enter your Green Meteor API credentials