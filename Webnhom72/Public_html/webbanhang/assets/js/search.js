const searchForm = document.querySelector("[data-search-form]");
const searchInput = document.querySelector("[data-search-input]");
const searchResults = document.querySelector("[data-search-results]");

if (searchForm && searchInput && searchResults) {
    let request = null;

    const hideResults = () => {
        searchResults.classList.remove("is-visible");
        searchResults.replaceChildren();
    };

    const showResults = (items) => {
        searchResults.replaceChildren();

        if (items.length === 0) {
            const empty = document.createElement("p");
            empty.className = "search-empty";
            empty.textContent = "Không tìm thấy sản phẩm.";
            searchResults.appendChild(empty);
        } else {
            items.forEach((item) => {
                const link = document.createElement("a");
                link.className = "search-result";
                link.href = `detail.php?id=${encodeURIComponent(item.id)}`;

                if (item.image) {
                    const image = document.createElement("img");
                    image.src = item.image;
                    image.alt = "";
                    image.loading = "lazy";
                    link.appendChild(image);
                } else {
                    const placeholder = document.createElement("span");
                    placeholder.className = "search-result-placeholder";
                    placeholder.textContent = "K";
                    link.appendChild(placeholder);
                }

                const content = document.createElement("span");
                const title = document.createElement("strong");
                title.textContent = item.title;
                const price = document.createElement("small");
                price.textContent = item.price;
                content.append(title, price);
                link.appendChild(content);
                searchResults.appendChild(link);
            });
        }

        searchResults.classList.add("is-visible");
    };

    searchInput.addEventListener("input", async () => {
        const query = searchInput.value.trim();
        if (request) request.abort();
        if (query === "") {
            hideResults();
            return;
        }

        request = new AbortController();
        try {
            const response = await fetch(`search_api.php?q=${encodeURIComponent(query)}`, {
                headers: { Accept: "application/json" },
                signal: request.signal
            });
            if (!response.ok) throw new Error(`Search request failed: ${response.status}`);
            const data = await response.json();
            showResults(Array.isArray(data.items) ? data.items : []);
        } catch (error) {
            if (error.name !== "AbortError") hideResults();
        } finally {
            request = null;
        }
    });

    document.addEventListener("click", (event) => {
        if (!searchForm.contains(event.target)) hideResults();
    });
}
