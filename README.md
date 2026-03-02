# tasteofcinemascraped

## Translation Quality Engine

A post-processing engine to review, score, and rewrite Arabic-translated cinematic content via OpenRouter.

### Configuration
Required Environment Variables:
- `OPENROUTER_API_KEY`: API key for OpenRouter (required, shared with Python script).
- `OPENROUTER_QUALITY_MODEL`: (Optional) Model to use for quality checks.

### REST API Endpoints
- `POST /tasteofcinemascraped/v1/quality/run`: Run engine on a post
- `GET /tasteofcinemascraped/v1/quality/jobs/{id}`: Get job status
- `POST /tasteofcinemascraped/v1/quality/jobs/{id}/resolve`: Approve/Reject job
- `GET /tasteofcinemascraped/v1/quality/audit`: Get audit log
- `GET|PUT /tasteofcinemascraped/v1/quality/settings`: Get/Update settings

See [developer guide](specs/001-translation-quality/quickstart.md) or [Arabic User Guide](specs/001-translation-quality/user-guide-ar.md) for details.
