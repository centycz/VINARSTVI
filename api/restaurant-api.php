// Updated function for action=bar-items
function barItemsAction() {
    // Fetching all pending/preparing items
    $items = fetchItemsFromDatabase();
    $filteredItems = array_filter($items, function($item) {
        return in_array($item['item_type'], ['pizza','pasta','predkrm','dezert','drink','pivo','vino','nealko','spritz','negroni','koktejl','digestiv','kava']);
    });

    // Sorting items with food types first
    usort($filteredItems, function($a, $b) {
        return ($a['item_type'] === 'food') ? -1 : 1;
    });

    return $filteredItems;
}