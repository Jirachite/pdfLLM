<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Chat</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body class="bg-gray-100 flex h-screen">
    <div class="w-1/4 bg-white border-l border-gray-200 p-4 flex flex-col h-full">
        <h2 class="text-xl font-bold mb-4">File Management</h2>
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700">Upload File</label>
            <input type="file" id="fileUpload" accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
        </div>
        <div class="flex-1 overflow-y-auto">
            <h3 class="text-lg font-semibold mb-2">Uploaded Files</h3>
            <div id="fileListMessage" class="text-gray-500"></div>
            <ul id="fileList" class="space-y-2"></ul>
        </div>
    </div>
    <div class="w-3/4 p-6 flex flex-col h-full">
        <div class="flex-1 overflow-y-auto mb-4 bg-white rounded-lg shadow p-4">
            <div class="flex">
                <div class="w-1/4 border-r border-gray-200 pr-4">
                    <h3 class="text-lg font-semibold mb-2">Chat Histories</h3>
                    <button id="newChat" class="mb-2 w-full bg-blue-500 text-white py-1 px-2 rounded hover:bg-blue-600">New Chat</button>
                    <ul id="chatHistoryList" class="space-y-1"></ul>
                </div>
                <div class="w-3/4 pl-4">
                    <h3 class="text-lg font-semibold mb-2">Chat</h3>
                    <div id="chatOutput" class="flex-1 overflow-y-auto mb-4 p-2 border border-gray-200 rounded h-96"></div>
                    <div class="flex">
                        <input id="chatInput" type="text" placeholder="Ask about your PDFs..." class="flex-1 p-2 border border-gray-300 rounded-l">
                        <button id="sendQuery" class="bg-blue-500 text-white p-2 rounded-r hover:bg-blue-600">Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let chatHistories = JSON.parse(localStorage.getItem('chatHistories')) || [];
        let currentChatId = chatHistories.length ? chatHistories[chatHistories.length - 1].id : null;
        console.log('Initial chatHistories:', chatHistories);

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        let isUploading = false;

        async function loadFiles() {
            const fileListMessage = document.getElementById('fileListMessage');
            fileListMessage.textContent = 'Loading files...';
            try {
                const response = await fetch('/files', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'Cache-Control': 'no-cache'
                    }
                });
                console.log('Files response:', response);
                if (!response.ok) {
                    throw new Error(`HTTP error! Status: ${response.status}`);
                }
                const files = await response.json();
                console.log('Files data:', files);
                const fileList = document.getElementById('fileList');
                fileList.innerHTML = '';
                if (files.length === 0) {
                    fileListMessage.textContent = 'No files uploaded.';
                    return;
                }
                fileListMessage.textContent = '';
                files.forEach(file => {
                    const li = document.createElement('li');
                    li.className = 'flex items-center justify-between p-2 bg-gray-50 rounded';
                    li.innerHTML = `
                        <div class="flex items-center">
                            <input type="checkbox" class="mr-2" value="${file.id}" onchange="updateSelectedFiles()">
                            <span>${file.filename}</span>
                        </div>
                        <div class="flex items-center">
                            <a href="/storage/uploads/${file.filename}" target="_blank" class="text-blue-500 hover:underline mr-2">Preview</a>
                            <a href="/debug/${file.id}" target="_blank" class="text-blue-500 hover:underline mr-2">Debug</a>
                            <button data-id="${file.id}" class="delete-file text-red-500 hover:text-red-700">Delete</button>
                        </div>
                    `;
                    fileList.appendChild(li);
                });

                document.querySelectorAll('.delete-file').forEach(button => {
                    button.addEventListener('click', async () => {
                        const fileId = button.getAttribute('data-id');
                        if (confirm('Are you sure you want to delete this file?')) {
                            try {
                                const response = await fetch(`/delete/${fileId}`, {
                                    method: 'DELETE',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken,
                                        'Accept': 'application/json'
                                    }
                                });
                                if (response.ok) {
                                    alert('File deleted successfully');
                                    loadFiles();
                                } else {
                                    const error = await response.json();
                                    alert(`Delete failed: ${error.error || 'Unknown error'}`);
                                }
                            } catch (error) {
                                console.error('Delete file error:', error);
                                alert('Delete failed: Network error');
                            }
                        }
                    });
                });
            } catch (error) {
                console.error('Error loading files:', error);
                fileListMessage.textContent = 'Error loading files.';
            }
        }

        let selectedFiles = [];
        function updateSelectedFiles() {
            selectedFiles = Array.from(document.querySelectorAll('#fileList input:checked')).map(input => input.value);
        }

        const uploadFile = debounce(async (e) => {
            if (isUploading) {
                console.log('Upload blocked: another upload in progress', new Date().toISOString());
                return;
            }
            isUploading = true;
            const fileInput = e.target;
            fileInput.disabled = true;
            const file = fileInput.files[0];
            const filename = file.name;

            try {
                console.log('Fetching upload token', new Date().toISOString(), filename);
                const tokenResponse = await fetch('/generate-upload-token', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ filename }),
                });

                if (!tokenResponse.ok) {
                    const error = await tokenResponse.json();
                    throw new Error(`Failed to get upload token: ${error.error || 'Unknown error'}`);
                }

                const { upload_token } = await tokenResponse.json();
                console.log('Upload token received', new Date().toISOString(), upload_token);

                const formData = new FormData();
                formData.append('file', file);
                formData.append('upload_token', upload_token);

                console.log('Upload started', new Date().toISOString(), filename);
                const response = await fetch('/upload', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });

                if (response.ok) {
                    alert('File uploaded successfully');
                    loadFiles();
                } else {
                    const error = await response.json();
                    alert(`Upload failed: ${error.error || 'Unknown error'}`);
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert(`Upload failed: ${error.message || 'Network error'}`);
            } finally {
                isUploading = false;
                fileInput.disabled = false;
                fileInput.value = '';
                console.log('Upload completed', new Date().toISOString());
            }
        }, 1000);

        document.getElementById('fileUpload').addEventListener('change', (e) => {
            console.log('File input changed', new Date().toISOString());
            uploadFile(e);
        });

        document.getElementById('sendQuery').addEventListener('click', async () => {
            const query = document.getElementById('chatInput').value.trim();
            if (!query || selectedFiles.length === 0) {
                alert('Please enter a query and select at least one file.');
                return;
            }

            const chatOutput = document.getElementById('chatOutput');
            chatOutput.innerHTML += `<div class="mb-2"><strong>You:</strong> ${query}</div>`;

            try {
                const response = await fetch('/query', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ query, file_ids: selectedFiles }),
                });

                if (response.ok) {
                    const data = await response.json();
                    chatOutput.innerHTML += `<div class="mb-2"><strong>Bot:</strong> ${data.response}</div>`;
                    chatOutput.scrollTop = chatOutput.scrollHeight;

                    const currentChat = chatHistories.find(chat => chat.id === currentChatId);
                    currentChat.messages.push({ query, response: data.response });
                    localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
                    console.log('Saved chatHistories:', chatHistories);
                } else {
                    const error = await response.json();
                    chatOutput.innerHTML += `<div class="mb-2 text-red-500"><strong>Error:</strong> ${error.error || 'Failed to process query'}</div>`;
                }
            } catch (error) {
                console.error('Query error:', error);
                chatOutput.innerHTML += `<div class="mb-2 text-red-500"><strong>Error:</strong> Network error</div>`;
            }

            document.getElementById('chatInput').value = '';
        });

        document.getElementById('newChat').addEventListener('click', () => {
            const newChat = {
                id: Date.now().toString(),
                name: `Chat ${chatHistories.length + 1}`,
                messages: [],
            };
            chatHistories.push(newChat);
            currentChatId = newChat.id;
            localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
            console.log('New chat created:', newChat);
            updateChatHistoryList();
            document.getElementById('chatOutput').innerHTML = '';
        });

        function deleteChat(chatId) {
            if (confirm('Are you sure you want to delete this chat?')) {
                chatHistories = chatHistories.filter(chat => chat.id !== chatId);
                if (currentChatId === chatId) {
                    currentChatId = chatHistories.length ? chatHistories[chatHistories.length - 1].id : null;
                }
                localStorage.setItem('chatHistories', JSON.stringify(chatHistories));
                console.log('Deleted chat:', chatId, 'Updated chatHistories:', chatHistories);
                updateChatHistoryList();
                if (currentChatId) {
                    const currentChat = chatHistories.find(c => c.id === currentChatId);
                    const chatOutput = document.getElementById('chatOutput');
                    chatOutput.innerHTML = currentChat.messages.map(msg => `
                        <div class="mb-2"><strong>You:</strong> ${msg.query}</div>
                        <div class="mb-2"><strong>Bot:</strong> ${msg.response}</div>
                    `).join('');
                    chatOutput.scrollTop = chatOutput.scrollHeight;
                } else {
                    document.getElementById('chatOutput').innerHTML = '';
                }
            }
        }

        function updateChatHistoryList() {
            const chatHistoryList = document.getElementById('chatHistoryList');
            chatHistoryList.innerHTML = '';
            chatHistories.forEach(chat => {
                const li = document.createElement('li');
                li.className = `flex items-center justify-between p-2 rounded cursor-pointer ${chat.id === currentChatId ? 'bg-blue-100' : 'hover:bg-gray-100'}`;
                li.innerHTML = `
                    <span>${chat.name}</span>
                    <button data-id="${chat.id}" class="delete-chat text-red-500 hover:text-red-700 text-sm">Delete</button>
                `;
                chatHistoryList.appendChild(li);
            });

            document.querySelectorAll('.delete-chat').forEach(button => {
                button.addEventListener('click', () => {
                    const chatId = button.getAttribute('data-id');
                    deleteChat(chatId);
                });
            });

            document.querySelectorAll('#chatHistoryList li').forEach(li => {
                li.querySelector('span').onclick = () => {
                    currentChatId = li.querySelector('button').getAttribute('data-id');
                    updateChatHistoryList();
                    const currentChat = chatHistories.find(c => c.id === currentChatId);
                    const chatOutput = document.getElementById('chatOutput');
                    chatOutput.innerHTML = currentChat.messages.map(msg => `
                        <div class="mb-2"><strong>You:</strong> ${msg.query}</div>
                        <div class="mb-2"><strong>Bot:</strong> ${msg.response}</div>
                    `).join('');
                    chatOutput.scrollTop = chatOutput.scrollHeight;
                };
            });
        }

        loadFiles();
        updateChatHistoryList();
    </script>
</body>
</html>