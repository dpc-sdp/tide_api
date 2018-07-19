@tide @jsonapi
Feature: Link Enhancer

  @api @nosuggest
  Scenario: Request to "test" individual endpoint with results.
    Given test content:
      | title         | path         | moderation_state | uuid                                | nid    | field_test_link             |
      | [TEST] Page 1 | /test-page-1 | published        | 99999999-aaaa-bbbb-ccc-000000000001 | 999991 | Page 2 - entity:node/999992 |
      | [TEST] Page 2 | /test-page-2 | published        | 99999999-aaaa-bbbb-ccc-000000000002 | 999992 |                             |

    Given I am an anonymous user

    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000001"
    Then the rest response status code should be 200
    And the response should be in JSON
    And the JSON node "links" should exist
    And the JSON node "links.self" should contain "api/v1/node/test"
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "data.attributes.field_test_link" should exist
    And the JSON node "data.attributes.field_test_link.uri" should be equal to "entity:node/test/99999999-aaaa-bbbb-ccc-000000000002"
    And the JSON node "data.attributes.field_test_link.title" should be equal to "Page 2"
    And the JSON node "data.attributes.field_test_link.url" should exist
    And the JSON node "data.attributes.field_test_link.entity" should exist
    And the JSON node "data.attributes.field_test_link.entity.uri" should be equal to "entity:node/999992"
    And the JSON node "data.attributes.field_test_link.entity.entity_type" should be equal to "node"
    And the JSON node "data.attributes.field_test_link.entity.entity_id" should be equal to "999992"
    And the JSON node "data.attributes.field_test_link.entity.bundle" should be equal to "test"
    And the JSON node "data.attributes.field_test_link.entity.uuid" should be equal to "99999999-aaaa-bbbb-ccc-000000000002"
