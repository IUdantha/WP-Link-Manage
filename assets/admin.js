(function ($) {
  $(function () {
    // Show "Add Link" panel
    $("#lm-add-link-btn").on("click", function () {
      $("#lm-add-link-panel").toggleClass("open");
    });

    // Expiry switch in Links form
    const expToggle = $("#lm_exp_toggle");
    const expEnabled = $("#lm_exp_enabled");
    const expDate = $("#lm_exp_date");
    function syncExpiryUI() {
      if (expToggle.is(":checked")) {
        expEnabled.val("1");
        expDate.show();
      } else {
        expEnabled.val("0");
        expDate.hide().val("");
      }
    }
    expToggle.on("change", syncExpiryUI);
    syncExpiryUI();

    // Student toggles (AJAX to user meta)
    $(".lm-toggle").on("change", function () {
      const $row = $(this).closest("tr");
      const userId = $row.data("user");
      const metaKey = $(this).data("key");
      const value = $(this).is(":checked") ? "1" : "0";

      $.post(LM_Ajax.ajax_url, {
        action: "lm_toggle_student",
        nonce: LM_Ajax.nonce,
        user_id: userId,
        meta_key: metaKey,
        value: value,
      }).fail(function () {
        alert("Failed to save. Please retry.");
      });
    });
  });
})(jQuery);
