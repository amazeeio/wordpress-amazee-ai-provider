=== AI Provider for amazee.ai ===
Contributors: amazeeio
Tags: AI, llm, gpt, artificial-intelligence, connector
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect the WordPress AI features to private AI hosting from amazee.ai for secure and privacy friendly language models on your site.

== Description ==

This plugin integrates [amazee.ai](https://amazee.ai) private AI hosting with WordPress AI features, enabling secure, sovereign, and GDPR-compliant AI capabilities on your site.

This plugin requires WordPress 7.0 or newer.

== Supported Operations & Models ==

= Chat Completions =

Fully supported for conversational AI, content generation, and chat-based interactions.

**Available Models:**
Models are dynamically loaded from your active LiteLLM region endpoint.

**Capabilities:**
- Standard text chat
- Image vision (for supported multimodal models)
- JSON output formatting
- Tool/function calling
- Streaming responses

== Installation ==

Install this plugin:

* Clone the repository into `wp-content/plugins/ai-provider-for-amazee-ai`
* Run `composer install --no-dev` inside the plugin directory
* Activate the plugin in WordPress

= Configuration =

1. **Obtain your credentials**:
   - Log into your account at [my.amazee.io](https://my.amazee.io) to obtain your endpoint URL and LLM token.
2. **Store AI Client Credentials**:
   - Navigate to Settings > Connectors (`/wp-admin/options-connectors.php`) in WordPress.
   - Locate the **amazee.ai** connector and enter your credential as `https://llm.<region>.amazee.ai/v1|<token>` (endpoint URL, a pipe, then your LLM token).
   - Alternatively define `AMAZEE_ENDPOINT_URL` and `AMAZEE_LLM_TOKEN` constants in `wp-config.php` (or set the `AMAZEEIO_API_KEY` environment variable to the `url|token` value) and skip the UI entirely.
   - Save the settings.
3. **Enable AI experiments** (optional):
   - To actually use the connector, install and activate the official [AI Experiments](https://wordpress.org/plugins/ai/) plugin.
   - Navigate to Settings > AI Experiments (`/options-general.php?page=ai-experiments`)
   - Select »Enable Experiments« and Save.

== Changelog ==

= 1.2 =
* Integrate with the WordPress 7.0 Connectors screen: the provider now declares API-key authentication so core manages its credential (setting `connectors_ai_amazeeio_api_key`, constant/env `AMAZEEIO_API_KEY`).
* The credential may include the endpoint: `https://llm.<region>.amazee.ai/v1|<token>`.
* Remove the legacy settings fields (the pre-7.0 AI plugin settings page no longer exists); legacy options are still read as fallback.

= 1.1 =
* Support Composer-based installs that provide a site-wide autoloader.
* Filter request parameters against each model's supported OpenAI parameters.
* Cache the model catalog for 12 hours per endpoint.
* Show an actionable message when the amazee.ai budget is exceeded.

= 1.0 =
* Initial release.
