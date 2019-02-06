@tide @jsonapi
Feature: Route lookup

  @api @nosuggest
  Scenario: Request to route lookup API to find a route by non-existing alias
    Given I am an anonymous user
    When I send a GET request to "api/v1/route?path=/test-non-existing-alias"
    Then the rest response status code should be 404
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/route"
    And the JSON node "data" should not exist
    And the JSON node "errors" should exist
    And the JSON array node "errors" should contain "Path not found." element

  Scenario: Request to route lookup API without a parameter specified.
    Given I am an anonymous user
    When I send a GET request to "api/v1/route"
    Then the rest response status code should be 400
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/route"
    And the JSON node "data" should not exist
    And the JSON node "errors" should exist
    And the JSON array node "errors" should contain "URL query parameter "path" is required." element

  @api @nosuggest
  Scenario: Request to route lookup API to find a route by existing alias
    Given test content:
      | title                    | body | moderation_state | path                        |
      | [TEST] Draft article     | test | draft            | /test-draft-article     |
      | [TEST] Published article | test | published        | /test-published-article |
    And I am an anonymous user

    # Anonymous users should not have access to unpublished nodes.
    When I send a GET request to "api/v1/route?path=/test-draft-article"
    Then the rest response status code should be 403
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/route"
    And the JSON node "data" should not exist
    And the JSON node "errors" should exist
    And the JSON array node "errors" should contain "Permission denied." element

    # Anonymous users should have access to published nodes.
    When I send a GET request to "api/v1/route?path=/test-published-article"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "data.links" should exist
    And the JSON node "data.links.self" should contain "api/v1/route"
    And the JSON node "data.attributes.bundle" should contain "test"
    And the JSON node "data.attributes.endpoint" should contain "api/v1/node/test/"
    And the JSON node "errors" should not exist

    Then the moderation state of test "[TEST] Published article" changes from "published" to "archived"
    And the moderation state of test "[TEST] Draft article" changes from "draft" to "published"
    And the cache has been cleared

    Then I send a GET request to "api/v1/route?path=/test-published-article"
    Then the rest response status code should be 403
    And the JSON node "errors" should exist
    And the JSON array node "errors" should contain "Permission denied." element

    Then I send a GET request to "api/v1/route?path=/test-draft-article"
    Then the rest response status code should be 200
    And the JSON node "data" should exist
    And the JSON node "errors" should not exist
