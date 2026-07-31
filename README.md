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
1. Obtain your credentials by logging into your account at [my.amazee.io](https://my.amazee.io).
2. Navigate to **Settings > Connectors** (`/wp-admin/options-connectors.php`) in WordPress.
3. Enter the credential for **amazee.ai** as `<endpoint URL>|<token>`, e.g.
   `https://llm.us103.amazee.ai/v1|sk-…`.
4. Save the settings.

Instead of the UI you can define `AMAZEE_ENDPOINT_URL` and `AMAZEE_LLM_TOKEN` in
`wp-config.php`, or set the `AMAZEEIO_API_KEY` environment variable to the
`url|token` value.

## Capabilities
Capabilities come from the `mode` the LiteLLM catalog reports per model, so a
region that offers image generation models advertises image generation without
any configuration:

| LiteLLM `mode`     | Exposed as                                                     |
| ------------------ | -------------------------------------------------------------- |
| `chat`             | Text generation and chat history; vision, function calling and JSON output when the model reports them |
| `image_generation` | Image generation (inline base64 output)                        |
| anything else      | Not exposed — `embedding`, `responses` and the audio modes need model classes the AI client has no OpenAI-compatible base class for |

## Model selection
Models are discovered from the region's LiteLLM catalog, so the available list
differs per region. WordPress picks the first discovered model that satisfies a
feature's requirements, unless a preference matches. This plugin appends the
`chat` alias to the AI plugin's text and vision preference lists and the
`text_to_image` alias to its image list, so those aliases win when the region
defines them and discovery applies when it does not.

To prefer other models, filter the AI plugin's lists — tuples are
`array( provider, model )`, in priority order:

```php
add_filter( 'wpai_preferred_text_models', function ( $models ) {
    return array_merge( array( array( 'amazeeio', 'claude-4-6-opus' ) ), $models );
}, 20 );
```

The same applies to `wpai_preferred_vision_models`. Per feature, the AI plugin's
**AI** screen can pin an exact provider and model once developer mode is enabled
(**Developer Tools** toggle); a pinned model is used with no fallback.

