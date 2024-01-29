<form action="/">
    <div class="relative border-2 border-gray-100 m-4 rounded-lg bg-slate-100">
        <div class="absolute top-4 left-3">
            <i
                class="fa fa-search text-gray-400 z-20 hover:text-gray-500"
            ></i>
        </div>
        <input
            type="text"
            name="search"
            class="h-14 w-full pl-10 pr-20 rounded-lg z-0 focus:shadow focus:outline-none bg-slate-100"
            placeholder="Search Album..."
        />
        <div class="absolute top-2 right-2">
            <button 
                type="reset"
                class="h-10 w-20 text-white rounded-lg bg-gray-500 hover:bg-red-600">
                    <a href="/">
                        Reset
                    </a>
                </button>
            <button
                type="submit"
                class="h-10 w-20 text-white rounded-lg bg-teal-600 hover:bg-red-600"
            >
                Search
            </button>
        </div>
    </div>
</form>