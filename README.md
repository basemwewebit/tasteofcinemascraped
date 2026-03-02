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

---

## Category Taxonomy

A strictly controlled, predefined taxonomy of 10 categories for the Arabic version, replacing dynamic category generation.

### [BREAKING] Dynamic Category Creation Disabled
Starting from version 1.1.0, the plugin no longer creates new WordPress categories during the import process. Every imported article is assigned to exactly one of the 10 predefined categories.

### Predefined Categories (Arabic)
1. **قوائم أفلام** (film-lists) — *Default*
2. **مقالات وتحليلات** (features)
3. **قوائم مخرجين وممثلين** (people-lists)
4. **قوائم متنوعة** (other-lists)
5. **مراجعات أفلام** (reviews)
6. **أفضل أفلام السنة** (best-of-year)
7. **أفلام حسب النوع** (by-genre)
8. **أفلام حسب البلد** (by-country)
9. **أفلام حسب العقد** (by-decade)
10. **مقارنات وتصنيفات** (rankings)

See [Category Quickstart](specs/002-predefined-categories/quickstart.md) for matching algorithm details and ops commands.
