# API Contracts

## `POST /wp-json/wp/v2/posts`
Creates a new post on the remote WordPress instance.

### Request Payload (JSON)
```json
{
  "title": "Post Title string",
  "content": "Post HTML content",
  "status": "publish",
  "categories": [12, 15],  // Requires term mapping prior to request
  "tags": [45]
}
```

### Response
- **201 Created**: Post successfully published.
- **401 Unauthorized**: Application Password invalid or missing.
- **403 Forbidden**: User does not have capability to publish posts.

## `POST /wp-json/wp/v2/media`
Uploads the featured image to the remote instance prior to publishing the post.

### Request Payload (Multipart Form Data)
File upload stream attached.

### Response
- **201 Created**: Returns the Attachment ID which must be subsequently attached to the `featured_media` property of the post creation payload.
