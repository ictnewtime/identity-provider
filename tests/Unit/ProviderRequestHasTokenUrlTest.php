<?php

namespace Tests\Unit;

use App\Http\Requests\ProviderRequest;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `has_token_url` inviato come form (come fa Swagger) arriva come testo: "true", non true.
 * Prima la regola `boolean` rifiutava "true"/"false" con un 422; funzionavano solo 1 e 0.
 */
class ProviderRequestHasTokenUrlTest extends TestCase
{
    /** Valida la richiesta come farebbe Laravel su una POST inviata come form. */
    private function validateForm(array $fields): array
    {
        $data = array_merge([
            "domain" => "example.com",
            "secret_key" => "2d6f5d6f8d6f2d6f5d6f8d6f2d6f5d6f",
            "logoutUrl" => "https://example.com/logout",
        ], $fields);

        $request = ProviderRequest::create("/manage/providers", "POST", $data);
        $request->setContainer($this->app)->setRedirector($this->app->make("redirect"));
        $request->validateResolved();

        return $request->validated();
    }

    public static function acceptedValues(): array
    {
        return [
            "true as text" => ["true", true],
            "false as text" => ["false", false],
            "1 as text" => ["1", true],
            "0 as text" => ["0", false],
        ];
    }

    #[DataProvider("acceptedValues")]
    public function test_has_token_url_sent_as_form_is_accepted(string $sent, bool $expected): void
    {
        $this->assertSame($expected, $this->validateForm(["has_token_url" => $sent])["has_token_url"]);
    }

    public function test_a_non_boolean_value_is_still_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->validateForm(["has_token_url" => "abc"]);
    }

    public function test_has_token_url_is_optional(): void
    {
        $this->assertArrayNotHasKey("has_token_url", $this->validateForm([]));
    }
}
