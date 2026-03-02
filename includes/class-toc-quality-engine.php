<?php
declare(strict_types=1);

class TOC_Quality_Exception extends Exception {}

class TOC_Quality_Engine {

	private const REGISTER_INSTRUCTIONS = array(
		'synopsis'  => "فصيح سردي رسمي مناسب للنقد السينمائي المنشور",
		'dialogue'  => "لهجة حوارية طبيعية تناسب شخصية المتحدث الثقافية دون تكلف",
		'review'    => "أسلوب نقدي أكاديمي رصين",
		'editorial' => "محرري محايد سليم",
	);

	public static function assess( string $text, string $content_type ): array {
		$register = self::REGISTER_INSTRUCTIONS[ $content_type ] ?? self::REGISTER_INSTRUCTIONS['editorial'];
		$system_prompt = "You are an expert Arabic cinematic translator and editor.
Evaluate the provided Arabic text against these 4 dimensions (0-100 total):
1. Literal accuracy (30%)
2. Cultural localization (30%)
3. Register/Tone (20%) - Target Register: $register
4. Fluency and readability (20%)

Output strictly JSON matching this schema:
{
  \"pre_score\": 90,
  \"dimension_scores\": {\"literal\": 27, \"cultural\": 27, \"register\": 18, \"fluency\": 18},
  \"issues\": [\"array of strings detailing max 5 major issues\"]
}";

		$messages = array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $text ),
		);

		return self::call_openrouter( $messages );
	}

	public static function extract_protected_terms( string $text ): array {
		$protected = array();
		if ( preg_match_all( '/([A-Za-z0-9\s\-\:]+)\s*\(\d{4}\)/u', $text, $matches ) ) {
			foreach ( $matches[0] as $i => $full_match ) {
				$protected[] = trim( $full_match );
				$protected[] = trim( $matches[1][$i] );
			}
		}
		return array_unique( array_filter( $protected ) );
	}

	public static function rewrite( string $text, string $content_type, array $issues, array $protected_terms ): array {
		$register = self::REGISTER_INSTRUCTIONS[ $content_type ] ?? self::REGISTER_INSTRUCTIONS['editorial'];
		
		$issues_str = empty( $issues ) ? 'None' : implode( "\n- ", $issues );
		$protected_str = empty( $protected_terms ) ? 'None' : implode( ", ", $protected_terms );

		$system_prompt = "You are an expert Arabic cinematic translator and editor.
Rewrite the provided Arabic text to fix the following issues:
- $issues_str

CRITICAL RULES:
1. Preserve these protected terms EXACTLY as written: $protected_str
2. Adhere strictly to this register: $register
3. Only change what is broken. Do not rewrite perfect sentences.

Output strictly JSON matching this schema:
{
  \"post_score\": 95,
  \"revised_text\": \"the full corrected Arabic text\",
  \"change_manifest\": [
    {
      \"id\": \"cr-001\",
      \"original\": \"the old segment\",
      \"revised\": \"the new segment\",
      \"category\": \"tonal\",
      \"rationale\": \"Arabic plain-language explanation\"
    }
  ]
}";

		$messages = array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user', 'content' => $text ),
		);

		return self::call_openrouter( $messages );
	}

	private static function call_openrouter( array $messages ): array {
		$api_key = $_SERVER['OPENROUTER_API_KEY'] ?? $_ENV['OPENROUTER_API_KEY'] ?? '';
		
		if ( empty( $api_key ) ) {
			$env_path = dirname( __DIR__ ) . '/tasteofcinemascraped/.env';
			if ( file_exists( $env_path ) ) {
				$env_content = file_get_contents( $env_path );
				if ( preg_match( '/^OPENROUTER_API_KEY\s*=\s*(.*)$/m', $env_content, $matches ) ) {
					$api_key = trim( $matches[1], "\"' \n\r\t" );
				}
			}
		}

		if ( empty( $api_key ) ) {
			throw new TOC_Quality_Exception( 'OPENROUTER_API_KEY is not configured.' );
		}

		$model = TOC_Quality_DB::get_model();
		if ( empty( $model ) ) {
			$model = 'anthropic/claude-3.5-sonnet';
		}

		$body = array(
			'model'           => $model,
			'messages'        => $messages,
			'temperature'     => 0,
			'response_format' => array( 'type' => 'json_object' ),
		);

		$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => 1200,
		) );

		if ( is_wp_error( $response ) ) {
			throw new TOC_Quality_Exception( 'API Request Failed: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_content = wp_remote_retrieve_body( $response );
		
		if ( $status_code !== 200 ) {
			throw new TOC_Quality_Exception( 'API Request returned HTTP ' . $status_code . ' - Body: ' . $body_content . ' - Key length: ' . strlen($api_key) . ' - Header: Authorization: Bearer ' . substr($api_key, 0, 5) . '...' . substr($api_key, -5) );
		}

		$json = json_decode( $body_content, true );

		if ( empty( $json['choices'][0]['message']['content'] ) ) {
			throw new TOC_Quality_Exception( 'Invalid response structure from OpenRouter.' );
		}

		$content = json_decode( $json['choices'][0]['message']['content'], true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new TOC_Quality_Exception( 'OpenRouter returned invalid JSON content.' );
		}

		return $content;
	}

	public static function process_job( int $job_id, bool $hint_provided = true ): void {
		try {
			$job = TOC_Quality_DB::get_job( $job_id, true );
			if ( ! $job ) {
				throw new Exception( "Job not found" );
			}

			if ( ! TOC_Quality_DB::transition_status( $job_id, 'processing' ) ) {
				throw new Exception( "Failed to transition job to processing" );
			}

			$raw_content = get_post_field( 'post_content', (int) $job['post_id'] );
			$model = TOC_Quality_DB::get_model() ?: 'anthropic/claude-3.5-sonnet';

			$assessment = self::assess( $raw_content, $job['content_type'] );
			$pre_score = (int) ( $assessment['pre_score'] ?? 0 );

			if ( $pre_score >= 85 ) {
				TOC_Quality_DB::transition_status( $job_id, 'auto-approved', array(
					'pre_score'       => $pre_score,
					'post_score'      => $pre_score,
					'change_manifest' => [],
					'change_count'    => 0,
					'model_version'   => $model,
				) );
				wp_update_post( array(
					'ID'          => (int) $job['post_id'],
					'post_status' => 'publish',
				) );
				return;
			}

			$protected = self::extract_protected_terms( $raw_content );
			$issues = $assessment['issues'] ?? [];

			$rewrite_result = self::rewrite( $raw_content, $job['content_type'], $issues, $protected );

			$post_score = (int) ( $rewrite_result['post_score'] ?? $pre_score );
			$revised_text = $rewrite_result['revised_text'] ?? $raw_content;
			$manifest = $rewrite_result['change_manifest'] ?? [];

			if ( ! $hint_provided ) {
				$manifest[] = array(
					'id' => 'cr-000',
					'original' => '',
					'revised' => '',
					'category' => 'tonal',
					'rationale' => 'لم يُحدَّد نوع المحتوى. تم تطبيق الأسلوب التحريري المحايد افتراضيًا. تحديد نوع المحتوى يُحسِّن دقة المعايرة.',
				);
			}

			$threshold = TOC_Quality_DB::get_threshold();
			$new_status = ( $post_score >= $threshold ) ? 'auto-approved' : 'flagged-for-review';

			TOC_Quality_DB::transition_status( $job_id, $new_status, array(
				'pre_score'       => $pre_score,
				'post_score'      => $post_score,
				'change_manifest' => $manifest,
				'change_count'    => count( $manifest ),
				'model_version'   => $model,
			) );

			$update_args = array(
				'ID'           => (int) $job['post_id'],
				'post_content' => $revised_text,
			);

			if ( $new_status === 'auto-approved' ) {
				$update_args['post_status'] = 'publish';
			}

			wp_update_post( $update_args );

		} catch ( Exception $e ) {
			if ( isset( $job_id ) && $job_id ) {
				TOC_Quality_DB::transition_status( $job_id, 'engine-unavailable', array(
					'metadata' => array( 'error' => $e->getMessage() ),
				) );
			}
		}
	}
}
