document.addEventListener('DOMContentLoaded', function() {
    const inboxTrigger = document.getElementById('inbox-trigger');
    const inboxMenu = inboxTrigger ? inboxTrigger.closest('.inbox-menu') : null;
    const notificationDot = inboxTrigger ? inboxTrigger.querySelector('.notification-dot') : null;

    const userTrigger = document.querySelector('.user-profile-trigger');
    const userMenu = userTrigger ? userTrigger.closest('.user-menu') : null;

    // Get the initial unread count from the data attribute (see navbar.php update)
    let currentUnreadCount = inboxTrigger ? parseInt(inboxTrigger.dataset.unreadCount) || 0 : 0;

    // Function to update the red dot visibility
    function updateNotificationDot() {
        if (notificationDot) {
            if (currentUnreadCount > 0) {
                notificationDot.style.display = 'block'; // Show red dot
            } else {
                notificationDot.style.display = 'none'; // Hide red dot
            }
        }
    }

    // Initialize dot state
    updateNotificationDot();

    // --- Notification Menu Toggle Logic (Click to Open/Close) ---
    if (inboxTrigger && inboxMenu) {
        inboxTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            if (userMenu) userMenu.classList.remove('is-open');
            
            inboxMenu.classList.toggle('is-open');
        });

        // --- Individual Notification Click Logic (Click to Read) ---
        const inboxList = document.querySelector('.inbox-list');
        if (inboxList) {
            inboxList.addEventListener('click', function(e) {
                const listItem = e.target.closest('.inbox-list-item');
                // Check if the item is unread
                if (listItem && listItem.classList.contains('is-unread')) {
                    const notificationId = listItem.dataset.notificationId;

                    if (!notificationId) return;

                    fetch('utils/markNotifications.php', { 
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ notification_id: notificationId })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            listItem.classList.remove('is-unread');
                            currentUnreadCount--;
                            updateNotificationDot(); // Update the dot based on new count
                        } else {
                            console.error('Failed to mark notification as read:', data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error marking notification as read:', error);
                    });
                }
            });
        }
    }

    // --- User Menu Logic (Click to Open/Close) ---
    if (userTrigger && userMenu) {
        userTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (inboxMenu) inboxMenu.classList.remove('is-open');

            userMenu.classList.toggle('is-open');
        });
    }

    // --- Global "Click Away" Listener ---
    document.addEventListener('click', function(e) {
        if (userMenu && userMenu.classList.contains('is-open') && !userMenu.contains(e.target)) {
            userMenu.classList.remove('is-open');
        }
        if (inboxMenu && inboxMenu.classList.contains('is-open') && !inboxMenu.contains(e.target)) {
            inboxMenu.classList.remove('is-open');
        }
    });
});