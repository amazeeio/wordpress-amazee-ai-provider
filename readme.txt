=== AI Provider for amazee.ai ===
Contributors: dan2k3k4
Tags: AI, llm, gpt, artificial-intelligence, connector
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.3
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

= Image Generation =

Supported in regions whose endpoint offers a model in `image_generation` mode. Such models are detected automatically from the model catalog, no configuration needed. Images are returned inline as base64 data.

Modes other than chat and image generation (embeddings, audio, responses) are not exposed yet.

== External Services ==

This plugin connects to the amazee.ai API to provide its functionality. It is not usable without an amazee.ai account and credentials.

It connects to the amazee.ai endpoint you configure (for example `https://llm.<region>.amazee.ai/v1`) in two situations:

* To retrieve the list of AI models available to your account (cached for 12 hours).
* To send prompts and receive AI generated responses whenever you or a plugin on your site uses the WordPress AI features with amazee.ai selected as provider. The content of the prompt (which may include text you or your users enter) and the chosen model parameters are sent to the endpoint.

No data is sent to amazee.ai until you configure your credentials, and no other data (such as analytics or telemetry) is collected by this plugin. Every request includes an `X-Amazee-Client` header identifying this plugin and its version.

This service is provided by amazee.ai: [terms and conditions](https://amazee.ai/terms-and-conditions), [privacy policy](https://amazee.ai/privacy-policy).

== Installation ==

= From within WordPress =

1. Visit Plugins > Add New.
2. Search for **AI Provider for amazee.ai**, then click Install Now and Activate.
3. Follow the Configuration steps below.

= Manual installation =

1. Download the plugin zip, then upload it via Plugins > Add New > Upload Plugin. Or extract it into `wp-content/plugins/` yourself.
2. Activate the plugin through the Plugins menu in WordPress.
3. Follow the Configuration steps below.

= Composer =

For sites managed with Composer:

    composer require wpackagist-plugin/ai-provider-for-amazee-ai

Released versions bundle their own autoloader, so there is no build step. Only if you install from a Git clone do you need to run `composer install --no-dev` inside the plugin directory.

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

= 1.3 =
* Expose image generation: models the endpoint reports with the `image_generation` mode are now advertised with the image generation capability, so features such as the AI plugin's image generation turn themselves on in regions that offer such a model.
* Prefer the region's `chat` and `text_to_image` aliases when a feature has no explicit model preference, instead of whichever model the catalog happens to list first.

= 1.2.1 =
* Rewrite the installation instructions for the WordPress.org release: install from Plugins > Add New, or upload the zip. Composer is now a note for Composer-managed sites rather than the only documented path.

= 1.2 =
* Integrate with the WordPress 7.0 Connectors screen: the provider now declares API-key authentication, so the credential is stored and resolved by the core AI Client and handed to the provider through it. The plugin never reads the stored credential itself.
* The credential may include the endpoint: `https://llm.<region>.amazee.ai/v1|<token>`.
* Send an `X-Amazee-Client` header identifying the plugin and version with every API request.

= 1.1 =
* Support Composer-based installs that provide a site-wide autoloader.
* Filter request parameters against each model's supported OpenAI parameters.
* Cache the model catalog for 12 hours per endpoint.
* Show an actionable message when the amazee.ai budget is exceeded.

= 1.0 =
* Initial release.
