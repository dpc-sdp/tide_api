@tide @jsonapi @install:jsonapi_menu_items
Feature: JSONAPI Menu Items

  @api @nosuggest
  Scenario: Request to ensure menu items endpoint is available in tide_api

    Given I am an anonymous user

    # Menu items without a menu supplied should be 404
    When I send a GET request to "/api/v1/menu_items"
    Then the response code should be 404

    # Menu items with a test menu setup
    When I send a GET request to "/api/v1/menu_items/admin"
    Then the response code should be 200
    And the response should be in JSON
    And the JSON node "data" should exist
    
