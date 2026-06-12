# amazee.ai AI Provider for WordPress

Connects WordPress core AI features to amazee.ai, enabling AI-powered capabilities via a LiteLLM-compatible provider integration.

## Requirements
- WordPress 7.0 or newer (which includes the WordPress AI Client in core).
- PHP 7.4 or newer.
- Composer (for installation from source).

## Installation
If installing from source:
1. Clone the repository into your WordPress plugins directory (`wp-content/plugins/amazee-ai-provider`).
2. Run `composer install --no-dev` inside the plugin directory.
3. Activate the plugin in the WordPress Admin Dashboard under **Plugins**.

## Configuration
1. Obtain your keys by logging into your account at [my.amazee.io](https://my.amazee.io).
2. Navigate to **Settings > Connectors** (`/wp-admin/options-connectors.php`) in WordPress.
3. Locate the **amazee.ai** credentials section and fill in:
   - **amazee.ai ENDPOINT_URL**: e.g., `https://llm.us103.amazee.ai/v1`
   - **amazee.ai LLM_TOKEN**: Your private LLM token.
4. Save the settings.

