# SocialSync Engine

A WordPress plugin that lets you publish captions and media (images and videos) directly to **Facebook** and **LinkedIn** from your WordPress admin dashboard — no third-party scheduling service required.

---

## Features

- **Publish to multiple platforms at once** — post to Facebook and LinkedIn simultaneously from a single interface.
- **Media support** — attach images (JPEG, PNG, GIF, WebP up to 10 MB) or videos (MP4, MOV up to 1 GB) via drag-and-drop or file picker.
- **Per-platform privacy controls** — choose the audience/visibility setting for each platform independently.
- **OAuth 2.0 authentication** — secure, token-based connection to Facebook and LinkedIn with CSRF state validation.
- **Connect / Disconnect controls** — manage platform connections at any time without leaving WordPress.
- **Credentials stored securely** — API keys are stored server-side in the WordPress database and never exposed to the browser.
- **Admin-only access** — all REST API endpoints require the `manage_options` capability.

---

## Requirements

| Requirement | Minimum version |
|---|---|
| WordPress | 6.0+ |
| PHP | 8.1+ |
| Node.js *(for development builds)* | 18+ |

---

## Installation

### From source

1. Clone or download this repository into your `wp-content/plugins/` directory:
   ```bash
   git clone <repo-url> wp-content/plugins/socialsync-engine
   ```

2. Install PHP dependencies:
   ```bash
   cd wp-content/plugins/socialsync-engine
   composer install --no-dev --optimize-autoloader
   ```

3. Install and build the JavaScript assets:
   ```bash
   npm install
   npm run build
   ```

4. Activate the plugin in **WordPress Admin → Plugins → Installed Plugins**.

---

## Configuration

### Step 1 — Create developer apps

**Facebook**

1. Go to [developers.facebook.com/apps](https://developers.facebook.com/apps) and create an app (type: **Business**).
2. Add the **Facebook Login** product to your app.
3. Under **Settings → Basic**, copy your **App ID** and **App Secret**.
4. Add your OAuth redirect URI to the app's allowed redirect URIs:
   ```
   https://your-site.com/wp-json/socialsync-engine/v1/oauth/facebook/callback
   ```

**LinkedIn**

1. Go to [linkedin.com/developers/apps](https://www.linkedin.com/developers/apps) and create an app.
2. Under the **Auth** tab, copy your **Client ID** and **Client Secret**.
3. Add your OAuth redirect URI to the app's **Authorized Redirect URLs**:
   ```
   https://your-site.com/wp-json/socialsync-engine/v1/oauth/linkedin/callback
   ```

---

### Step 2 — Save credentials in WordPress

1. In your WordPress Admin, navigate to **SocialSync Engine → Settings**.
2. Enter your **Facebook App ID** and **App Secret**, then click **Save Credentials**.
3. Enter your **LinkedIn Client ID** and **Client Secret**, then click **Save Credentials**.

---

### Step 3 — Connect your accounts

1. Switch to the **Publish** tab.
2. Click **Connect** next to Facebook or LinkedIn.
3. Complete the OAuth flow in the popup/redirect — you will be redirected back to the plugin page once connected.
4. The platform card will show **Connected** with your account name.

---

## Usage

1. Navigate to **SocialSync Engine** in the WordPress admin sidebar.
2. On the **Publish** tab:
   - Enable the platforms you want to post to by checking the **Post to Facebook / LinkedIn** checkbox.
   - Optionally select a privacy/visibility setting for each platform.
   - Write your caption in the text area.
   - Optionally drag and drop (or click to select) an image or video.
   - Click **Publish Now**.
3. A success or error banner will confirm the result for each platform.

---

## REST API Reference

All endpoints live under the namespace `socialsync-engine/v1` and require WordPress admin authentication (nonce sent as the `X-WP-Nonce` header) unless noted otherwise.

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/oauth/{platform}/connect` | Returns the OAuth authorization URL for the given platform. |
| `GET` | `/oauth/{platform}/callback` | Handles the OAuth callback; exchanges the authorization code for an access token. *(Public — required by OAuth spec.)* |
| `POST` | `/oauth/{platform}/disconnect` | Removes the stored access token for the given platform. |
| `GET` | `/oauth/settings` | Returns stored API credentials (secrets are masked). |
| `POST` | `/oauth/settings` | Saves API credentials for a platform. |
| `GET` | `/status` | Returns connection status for all platforms. |
| `POST` | `/publish` | Publishes a post (multipart/form-data with `caption`, `platforms`, `media`, `facebook_privacy`, `linkedin_privacy`). |

`{platform}` is either `facebook` or `linkedin`.

---

## Project Structure

```
socialsync-engine/
├── includes/
│   ├── SocialSyncEngine.php          # Main plugin class (singleton)
│   ├── Assets.php                    # Script/style enqueueing
│   ├── Admin/
│   │   ├── AdminMenu.php             # Registers the admin menu page
│   │   └── REST/
│   │       ├── OAuthController.php   # OAuth connect / callback / disconnect / settings
│   │       ├── PublishController.php # Post publishing endpoint
│   │       └── StatusController.php  # Connection status endpoint
│   └── SocialHandlers/
│       ├── AbstractSocialHandler.php # Base handler with shared OAuth logic
│       ├── FacebookHandler.php       # Facebook Graph API integration
│       └── LinkedInHandler.php       # LinkedIn API integration
├── src/
│   ├── admin.js                      # JS entry point
│   ├── styles/admin.css
│   └── Components/
│       ├── App.jsx                   # Root component, tab routing
│       ├── Dashboard.jsx             # Publish tab
│       ├── Settings.jsx              # Settings tab (API credentials)
│       ├── ConnectionStatus.jsx      # Per-platform connect/disconnect UI
│       ├── MediaDropzone.jsx         # Drag-and-drop media uploader
│       └── PrivacyToggle.jsx         # Audience/privacy selector
├── build/                            # Compiled JS/CSS (generated)
├── vendor/                           # Composer dependencies (generated)
├── composer.json
├── socialsync-engine.php             # Plugin entry point
└── README.md
```

---

## Development

Install all dependencies and start the development watcher:

```bash
composer install
npm install
npm run start      # Webpack dev mode with file watching
```

Build for production:

```bash
npm run build
```

---

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html)
