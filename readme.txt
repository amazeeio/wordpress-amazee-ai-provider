=== AI Provider for amazee.ai ===
Contributors: amazee.ai
Tags: AI, llm, gpt, artificial-intelligence, connector, amazeeio
Requires at least: 7.0
Tested up to: 7.0
Stable tag: 1.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connects WordPress AI to amazee.ai private AI hosting, enabling AI-powered features via an OpenAI/LiteLLM-compatible provider integration.

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

* Clone the repository into `wp-content/plugins/amazee-ai-provider`
* Run `composer install --no-dev` inside the plugin directory
* Activate the plugin in WordPress

= Configuration =

1. **Obtain your credentials**:
   - Log into your account at [my.amazee.io](https://my.amazee.io) to obtain your endpoint URL and LLM token.
2. **Store AI Client Credentials**:
   - Navigate to Settings > Connectors (`/wp-admin/options-connectors.php`) in WordPress.
   - Locate the **amazee.ai** section and fill in:
     - **amazee.ai ENDPOINT_URL**: e.g. `https://llm.us103.amazee.ai/v1`
     - **amazee.ai LLM_TOKEN**: Your private LLM token.
   - Save the settings.
3. **Enable AI experiments** (optional):
   - To actually use the connector, install and activate the official [AI Experiments](https://wordpress.org/plugins/ai/) plugin.
   - Navigate to Settings > AI Experiments (`/options-general.php?page=ai-experiments`)
   - Select »Enable Experiments« and Save.
