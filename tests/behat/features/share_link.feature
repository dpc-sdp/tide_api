@tide @jsonapi @install:tide_share_link
Feature: Share Link

  @api @nosuggest
  Scenario: Request to "test" individual endpoint with Share Link Token.
    Given test content:
      | title             | path             | moderation_state | uuid                                | nid    |
      | [TEST] Page title | /test-page-alias | draft            | 99999999-aaaa-bbbb-ccc-000000000000 | 999999 |
    Given share_link_token share_link_token entity:
      | name       | status | uid | nid    | token                               |
      | Test token | 1      | 1   | 999999 | 99999999-aaaa-bbbb-ccc-000000000001 |

    Given I am an anonymous user

    # Anon user can't see the draft.
    When I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000"
    Then the response code should be 404

    # Share link without the correspond NID can't be retrieved.
    When I send a GET request to "api/v1/share_link/99999999-aaaa-bbbb-ccc-000000000001"
    Then the response code should be 404

    # Share link with the correspond NID.
    When I send a GET request to "api/v1/share_link/99999999-aaaa-bbbb-ccc-000000000001/999999"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    And the JSON node "data.type" should be equal to "share_link_token--share_link_token"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And the JSON node "data.attributes" should exist
    And the JSON node "data.attributes.nid" should exist
    And the JSON node "data.attributes.nid" should be equal to "999999"
    And the JSON node "data.relationships" should exist
    And the JSON node "data.relationships.shared_node" should exist
    And the JSON node "data.relationships.shared_node.data" should exist
    And the JSON node "data.relationships.shared_node.data.type" should exist
    And the JSON node "data.relationships.shared_node.data.type" should be equal to "node--test"
    And the JSON node "data.relationships.shared_node.data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000000"

    # Retrieve the draft with Share Link token header.
    When I set "X-Share-Link-Token" header equal to "99999999-aaaa-bbbb-ccc-000000000001"
    And I send a GET request to "api/v1/node/test/99999999-aaaa-bbbb-ccc-000000000000"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "data.type" should be equal to "node--test"
    And the JSON node "data.id" should be equal to "99999999-aaaa-bbbb-ccc-000000000000"
    And the JSON node "data.attributes" should exist
    And the JSON node "data.attributes.title" should be equal to "[TEST] Page title"
