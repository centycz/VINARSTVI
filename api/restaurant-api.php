// Updated function for action=bar-items
function barItemsAction() {
    // COMMENT_MARKER_FOR_FUTURE_REFERENCE: Modified to return all pending/preparing items regardless of item_type
    // Fetching all pending/preparing items
    $items = fetchItemsFromDatabase();
    
    // COMMENTED OUT FOR TRACEABILITY: Previous logic filtered by specific item types
    // $filteredItems = array_filter($items, function($item) {
    //     return in_array($item['item_type'], ['pizza','pasta','predkrm','dezert','drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv','kava']);
    // });

    // Return all items with pending/preparing status regardless of item_type
    $filteredItems = array_filter($items, function($item) {
        return in_array($item['status'], ['pending', 'preparing']);
    });

    // Sorting items with food types first
    usort($filteredItems, function($a, $b) {
        return ($a['item_type'] === 'food') ? -1 : 1;
    });

    return $filteredItems;
}